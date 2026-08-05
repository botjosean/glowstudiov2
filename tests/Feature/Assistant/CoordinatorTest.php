<?php

namespace Tests\Feature\Assistant;

use App\Models\Provider;
use App\Models\Service;
use App\Support\Assistant\AssistantUnavailable;
use App\Support\Assistant\Coordinator;
use App\Support\Kapso\InboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoordinatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.api_key' => 'test-api-key',
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.groq.api_key' => 'test-groq-key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'openai/gpt-oss-120b',
            'services.groq.max_iterations' => 3,
        ]);
    }

    public function test_it_returns_the_models_answer_when_no_tool_is_needed(): void
    {
        $this->fakeGroq([$this->text('¡Hola! ¿En qué te ayudo?')]);

        $reply = $this->coordinator()->reply($this->message('hola'), $this->provider());

        $this->assertSame('¡Hola! ¿En qué te ayudo?', $reply);
    }

    /**
     * The whole point of the loop: the tool actually runs on the server and its
     * real result is what the model then speaks from.
     */
    public function test_it_runs_a_proposed_tool_and_feeds_the_real_result_back(): void
    {
        $provider = $this->provider();
        Service::factory()->for($provider)->create(['name' => 'Balayage', 'price' => 200, 'duration_minutes' => 180]);

        $this->fakeGroq([
            $this->toolCall('listar_servicios'),
            $this->text('Tenemos Balayage por $200. ¿Te reservo?'),
        ]);

        $reply = $this->coordinator()->reply($this->message('qué servicios tienen'), $provider);

        $this->assertSame('Tenemos Balayage por $200. ¿Te reservo?', $reply);

        // The second call must carry the tool result, or the model was answering
        // from nothing.
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            $roles = array_column($request['messages'], 'role');

            return in_array('tool', $roles, true)
                && str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE) ?: '', 'Balayage');
        });
    }

    public function test_the_system_prompt_carries_the_business_notes_and_never_a_secret(): void
    {
        $this->fakeGroq([$this->text('Hola')]);

        $this->coordinator()->reply($this->message('hola'), $this->provider());

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            $system = $request['messages'][0]['content'];

            return $request['messages'][0]['role'] === 'system'
                && str_contains($system, 'Glow Studio')
                && str_contains($system, 'America/New_York')
                && str_contains($system, 'INFORMACIÓN DEL NEGOCIO')
                && ! str_contains($system, 'test-groq-key')
                && ! str_contains($system, 'test-api-key');
        });
    }

    /**
     * A model that never stops calling tools must not hold a worker forever.
     */
    public function test_it_gives_up_after_the_iteration_limit(): void
    {
        $this->fakeGroq([
            $this->toolCall('listar_servicios'),
            $this->toolCall('listar_servicios'),
            $this->toolCall('listar_servicios'),
            $this->toolCall('listar_servicios'),
        ]);

        $this->expectException(AssistantUnavailable::class);

        $this->coordinator()->reply($this->message('hola'), $this->provider());
    }

    public function test_a_rate_limit_is_retryable_and_honours_the_requested_wait(): void
    {
        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => []]),
            'api.groq.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429, ['retry-after' => '17']),
        ]);

        try {
            $this->coordinator()->reply($this->message('hola'), $this->provider());
            $this->fail('Expected the rate limit to surface.');
        } catch (AssistantUnavailable $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(17, $exception->retryAfterSeconds);
        }
    }

    /**
     * Groq reports a malformed tool call as a 400. Retrying reproduces it
     * exactly, so it must not consume a retry.
     */
    public function test_a_rejected_request_is_not_retryable(): void
    {
        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => []]),
            'api.groq.com/*' => Http::response(['error' => ['message' => 'failed_generation']], 400),
        ]);

        try {
            $this->coordinator()->reply($this->message('hola'), $this->provider());
            $this->fail('Expected the rejection to surface.');
        } catch (AssistantUnavailable $exception) {
            $this->assertFalse($exception->retryable);
        }
    }

    /**
     * History is a convenience, not a dependency: Kapso being unreachable must
     * not cost the client an answer.
     */
    public function test_it_still_answers_when_the_history_cannot_be_loaded(): void
    {
        Http::fake([
            'api.kapso.ai/*' => Http::response('boom', 500),
            'api.groq.com/*' => Http::response($this->text('Hola')),
        ]);

        $this->assertSame('Hola', $this->coordinator()->reply($this->message('hola'), $this->provider()));
    }

    /**
     * @param  list<array<mixed>>  $responses
     */
    private function fakeGroq(array $responses): void
    {
        $sequence = Http::sequence();

        foreach ($responses as $response) {
            $sequence->push($response);
        }

        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => []]),
            'api.groq.com/*' => $sequence,
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function text(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    /**
     * @return array<mixed>
     */
    private function toolCall(string $name, string $arguments = '{}'): array
    {
        return ['choices' => [['message' => [
            'role' => 'assistant',
            'content' => null,
            // gpt-oss returns this extra field; the loop must tolerate it.
            'reasoning' => 'El usuario pregunta por los servicios.',
            'tool_calls' => [[
                'id' => 'fc_'.$name,
                'type' => 'function',
                'function' => ['name' => $name, 'arguments' => $arguments],
            ]],
        ]]]];
    }

    private function coordinator(): Coordinator
    {
        return app(Coordinator::class);
    }

    private function provider(): Provider
    {
        return Provider::factory()->published()->create([
            'public_name' => 'Patricia moreno',
            'timezone' => 'America/New_York',
            'whatsapp_phone_number_id' => '868324373028256',
        ]);
    }

    private function message(string $text): InboundMessage
    {
        $message = InboundMessage::fromDelivery([
            'message' => [
                'id' => 'wamid.abc',
                'type' => 'text',
                'from' => '12056455856',
                'text' => ['body' => $text],
                'kapso' => ['direction' => 'inbound', 'origin' => 'cloud_api'],
            ],
            'conversation' => [
                'id' => 'conv_1',
                'phone_number' => '12056455856',
                'phone_number_id' => '868324373028256',
                'kapso' => ['contact_name' => 'Josean Sosa'],
            ],
            'phone_number_id' => '868324373028256',
        ]);

        $this->assertNotNull($message);

        return $message;
    }
}
