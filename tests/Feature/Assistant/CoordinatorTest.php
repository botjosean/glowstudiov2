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
            'services.assistant.api_key' => 'test-groq-key',
            'services.assistant.base_url' => 'https://openrouter.ai/api/v1',
            'services.assistant.model' => 'openai/gpt-oss-120b',
            'services.assistant.max_iterations' => 3,
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
     * The gap a real conversation hit: a client asked "¿a dónde voy?" right
     * after booking and the assistant had nothing, even though the profile
     * panel already had an address saved — it was just never read into the
     * prompt.
     */
    public function test_the_system_prompt_carries_the_studio_address_when_set(): void
    {
        $this->fakeGroq([$this->text('Hola')]);

        $provider = $this->provider();
        $provider->update(['address_line' => '123 Peachtree St, Atlanta, GA']);

        $this->coordinator()->reply($this->message('hola'), $provider);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            $system = $request['messages'][0]['content'];

            return str_contains($system, 'UBICACIÓN')
                && str_contains($system, '123 Peachtree St, Atlanta, GA');
        });
    }

    /**
     * A mobile professional has no studio address at all — the assistant
     * must say the service area, not a street that does not exist.
     */
    public function test_the_system_prompt_carries_the_service_area_for_mobile_providers(): void
    {
        $this->fakeGroq([$this->text('Hola')]);

        $provider = $this->provider();
        $provider->update(['is_mobile' => true, 'service_area' => 'Buckhead y Midtown', 'address_line' => null]);

        $this->coordinator()->reply($this->message('hola'), $provider);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            $system = $request['messages'][0]['content'];

            return str_contains($system, 'UBICACIÓN')
                && str_contains($system, 'Buckhead y Midtown');
        });
    }

    /**
     * Neither field set (Vanessa's situation before she fills her profile):
     * the section must not appear at all, rather than printing an empty
     * "UBICACIÓN" heading with nothing under it.
     */
    public function test_the_system_prompt_omits_the_location_section_when_nothing_is_set(): void
    {
        $this->fakeGroq([$this->text('Hola')]);

        $this->coordinator()->reply($this->message('hola'), $this->provider());

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            return ! str_contains($request['messages'][0]['content'], 'UBICACIÓN');
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
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'rate limited']], 429, ['retry-after' => '17']),
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
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'failed_generation']], 400),
        ]);

        try {
            $this->coordinator()->reply($this->message('hola'), $this->provider());
            $this->fail('Expected the rejection to surface.');
        } catch (AssistantUnavailable $exception) {
            $this->assertFalse($exception->retryable);
        }
    }

    /**
     * An empty generation was terminal, so the model's first bad roll sent the
     * client to a human. It clears on a second attempt far more often than not,
     * and RespondToWhatsAppMessage claims the reply scope before sending, so
     * the retry cannot deliver the same answer twice.
     */
    public function test_an_empty_generation_is_retryable(): void
    {
        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => []]),
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
            ]),
        ]);

        try {
            $this->coordinator()->reply($this->message('hola'), $this->provider());
            $this->fail('Expected the empty generation to surface.');
        } catch (AssistantUnavailable $exception) {
            $this->assertTrue($exception->retryable);
        }
    }

    /** A 200 carrying neither a message nor an error is a provider blip, not a shape this parser will never learn. */
    public function test_a_200_with_no_message_is_retryable(): void
    {
        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => []]),
            'openrouter.ai/*' => Http::response(['choices' => []]),
        ]);

        try {
            $this->coordinator()->reply($this->message('hola'), $this->provider());
            $this->fail('Expected the malformed body to surface.');
        } catch (AssistantUnavailable $exception) {
            $this->assertTrue($exception->retryable);
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
            'openrouter.ai/*' => Http::response($this->text('Hola')),
        ]);

        $this->assertSame('Hola', $this->coordinator()->reply($this->message('hola'), $this->provider()));
    }

    /**
     * Kapso keeps every message a business ever exchanged and offers no way to
     * delete one, so a polluted test conversation would otherwise stay in the
     * model's context forever. The cutoff is how a conversation gets a clean
     * slate without destroying the business's data.
     */
    public function test_history_before_the_cutoff_is_ignored(): void
    {
        config(['services.assistant.history_since' => '2026-08-05T21:00:00Z']);

        $this->fakeWithHistory([
            ['id' => 'wamid.old', 'text' => ['body' => 'mensaje viejo de una prueba'],
                'timestamp' => '1785900000', 'kapso' => ['direction' => 'inbound']],
            ['id' => 'wamid.new', 'text' => ['body' => 'mensaje nuevo de verdad'],
                'timestamp' => '1785970000', 'kapso' => ['direction' => 'inbound']],
        ]);

        $this->coordinator()->reply($this->message('hola'), $this->provider());

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            $sent = json_encode($request['messages'], JSON_UNESCAPED_UNICODE) ?: '';

            return ! str_contains($sent, 'mensaje viejo')
                && str_contains($sent, 'mensaje nuevo');
        });
    }

    public function test_without_a_cutoff_the_whole_history_is_replayed(): void
    {
        config(['services.assistant.history_since' => null]);

        $this->fakeWithHistory([
            ['id' => 'wamid.old', 'text' => ['body' => 'mensaje viejo de una prueba'],
                'timestamp' => '1785970000', 'kapso' => ['direction' => 'inbound']],
        ]);

        $this->coordinator()->reply($this->message('hola'), $this->provider());

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'chat/completions')
                && str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE) ?: '', 'mensaje viejo');
        });
    }

    /**
     * A malformed date must not silently drop everything: too quiet is still
     * wrong when the cause is a typo.
     */
    public function test_an_unparseable_cutoff_is_ignored_rather_than_dropping_everything(): void
    {
        config(['services.assistant.history_since' => 'el martes pasado']);

        $this->fakeWithHistory([
            ['id' => 'wamid.old', 'text' => ['body' => 'mensaje viejo de una prueba'],
                'timestamp' => '1785970000', 'kapso' => ['direction' => 'inbound']],
        ]);

        $this->coordinator()->reply($this->message('hola'), $this->provider());

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'chat/completions')
                && str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE) ?: '', 'mensaje viejo');
        });
    }

    /**
     * The professional answers from her own phone; the client writes again. If
     * the assistant also answers, the client is talking to two voices that may
     * contradict each other.
     */
    public function test_it_stays_quiet_when_a_person_answered_by_hand(): void
    {
        $this->fakeWithHistory([
            ['id' => 'wamid.manual', 'text' => ['body' => 'Buenas, dime'],
                'timestamp' => (string) now()->subMinutes(5)->getTimestamp(),
                'from' => '14044518022', 'kapso' => ['direction' => 'outbound']],
        ]);

        $this->assertNull($this->coordinator()->reply($this->message('hola'), $this->provider()));

        // And it did not even ask the model, so it costs nothing either.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'chat/completions'));
    }

    /**
     * The distinguishing signal: messages this app sent carry no `from`, so the
     * assistant must not mistake its own replies for a person taking over — that
     * would silence it after its very first answer.
     */
    public function test_its_own_replies_do_not_look_like_a_person(): void
    {
        $this->fakeWithHistory([
            ['id' => 'wamid.ours', 'text' => ['body' => 'Tenemos balayage por $200'],
                'timestamp' => (string) now()->subMinutes(1)->getTimestamp(),
                'from' => '', 'kapso' => ['direction' => 'outbound']],
        ]);

        $this->assertSame('Hola', $this->coordinator()->reply($this->message('hola'), $this->provider()));
    }

    /**
     * And a hand-off is not forever: a manual reply from this morning must not
     * keep the assistant out of tonight's conversation.
     */
    public function test_an_old_manual_reply_does_not_silence_it_forever(): void
    {
        $this->fakeWithHistory([
            ['id' => 'wamid.manual', 'text' => ['body' => 'Buenas'],
                'timestamp' => (string) now()->subHours(3)->getTimestamp(),
                'from' => '14044518022', 'kapso' => ['direction' => 'outbound']],
        ]);

        $this->assertSame('Hola', $this->coordinator()->reply($this->message('hola'), $this->provider()));
    }

    /**
     * The real failure this guards against: the assistant fails once, sends
     * "no puedo responderte yo, ya avisé al salón", and the client's very
     * next message must not hit the same failure again — two wait messages
     * in a row undermines the hand-off instead of honouring it.
     */
    public function test_it_stays_quiet_after_recently_handing_off_to_a_person(): void
    {
        $this->fakeWithHistory([
            ['id' => 'wamid.wait', 'text' => ['body' => Coordinator::WAIT_MESSAGE],
                'timestamp' => (string) now()->subMinutes(10)->getTimestamp(),
                'from' => '', 'kapso' => ['direction' => 'outbound']],
        ]);

        $this->assertNull($this->coordinator()->reply($this->message('hola'), $this->provider()));

        // It did not even ask the model, so a client writing again while
        // waiting does not cost anything either.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'chat/completions'));
    }

    /**
     * The pause has an end: a hand-off from hours ago must not mute the
     * assistant on that number forever.
     */
    public function test_an_old_handoff_does_not_silence_it_forever(): void
    {
        $this->fakeWithHistory([
            ['id' => 'wamid.wait', 'text' => ['body' => Coordinator::WAIT_MESSAGE],
                'timestamp' => (string) now()->subHours(3)->getTimestamp(),
                'from' => '', 'kapso' => ['direction' => 'outbound']],
        ]);

        $this->assertSame('Hola', $this->coordinator()->reply($this->message('hola'), $this->provider()));
    }

    /**
     * @param  list<array<mixed>>  $rows
     */
    private function fakeWithHistory(array $rows): void
    {
        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => $rows]),
            'openrouter.ai/*' => Http::response($this->text('Hola')),
        ]);
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
            'openrouter.ai/*' => $sequence,
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
