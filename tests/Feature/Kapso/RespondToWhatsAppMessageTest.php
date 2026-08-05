<?php

namespace Tests\Feature\Kapso;

use App\Jobs\RespondToWhatsAppMessage;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\KapsoClient;
use App\Support\Kapso\ReplyPolicy;
use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RespondToWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.api_key' => 'test-api-key',
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.kapso.graph_version' => 'v24.0',
            // Who may be answered is ReplyPolicy's concern; these cases are
            // about the send itself.
            'services.kapso.reply_mode' => 'everyone',
        ]);
    }

    public function test_it_echoes_the_message_back_through_the_number_it_arrived_on(): void
    {
        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.outbound']]]),
        ]);

        $this->runJob($this->job());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.kapso.ai/meta/whatsapp/v24.0/1280262548502304/messages'
                && $request->method() === 'POST'
                && $request->header('X-API-Key') === ['test-api-key']
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '16315551181'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Recibí: Hola, quiero una cita';
        });

        Http::assertSentCount(1);
    }

    public function test_it_sends_exactly_one_reply_per_inbound_message(): void
    {
        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.outbound']]]),
        ]);

        $job = $this->job();

        $this->runJob($job);
        $this->runJob($job);

        Http::assertSentCount(1);
    }

    /**
     * A Business-Scoped User ID conversation carries no phone number, so there
     * is nothing to put in the Cloud API's `to` field. That must be a logged
     * skip, not a crashed worker.
     */
    public function test_a_message_without_a_phone_number_is_skipped_without_sending(): void
    {
        Http::fake();

        $message = InboundMessage::fromDelivery($this->delivery(withPhone: false));

        $this->assertNotNull($message);
        $this->assertNull($message->fromPhone);
        $this->assertSame('US.13491208655302741918', $message->businessScopedUserId);

        $this->runJob(new RespondToWhatsAppMessage($message));

        Http::assertNothingSent();
    }

    /**
     * A failed send must not record a reply, or the retry would decide the
     * client had already been answered and stay silent forever.
     */
    public function test_a_failed_send_leaves_no_reply_claim(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'boom'], 500),
        ]);

        try {
            $this->runJob($this->job());
            $this->fail('Expected the send failure to surface so the queue can retry.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse(
            app(WebhookDeduplicator::class)->claimed(WebhookDeduplicator::SCOPE_REPLY, 'wamid.111')
        );
    }

    /**
     * The job re-checks the policy even though the controller already did: a
     * job queued before the allowlist was tightened must not still go out.
     */
    public function test_the_job_refuses_to_send_to_a_number_outside_the_allowlist(): void
    {
        Http::fake();

        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '12056455856',
        ]);

        $this->runJob($this->job());

        Http::assertNothingSent();
    }

    /**
     * The safety property that matters most: a missing or misspelled
     * configuration must silence the assistant, never let it answer a real
     * salon's entire client list.
     */
    public function test_an_unset_configuration_answers_nobody(): void
    {
        Http::fake();

        config([
            'services.kapso.reply_mode' => null,
            'services.kapso.test_recipients' => null,
        ]);

        $this->runJob($this->job());

        Http::assertNothingSent();
    }

    private function runJob(RespondToWhatsAppMessage $job): void
    {
        $job->handle(
            app(KapsoClient::class),
            app(WebhookDeduplicator::class),
            app(ReplyPolicy::class),
        );
    }

    private function job(): RespondToWhatsAppMessage
    {
        $message = InboundMessage::fromDelivery($this->delivery());

        $this->assertNotNull($message);

        return new RespondToWhatsAppMessage($message);
    }

    /**
     * @return array<mixed>
     */
    private function delivery(bool $withPhone = true): array
    {
        $message = [
            'id' => 'wamid.111',
            'type' => 'text',
            'text' => ['body' => 'Hola, quiero una cita'],
            'kapso' => ['direction' => 'inbound', 'origin' => 'cloud_api'],
        ];

        $conversation = [
            'id' => 'conv_123',
            'phone_number_id' => '1280262548502304',
        ];

        if ($withPhone) {
            $message['from'] = '16315551181';
            $conversation['phone_number'] = '16315551181';
        } else {
            $message['from_user_id'] = 'US.13491208655302741918';
            $conversation['business_scoped_user_id'] = 'US.13491208655302741918';
        }

        return [
            'message' => $message,
            'conversation' => $conversation,
            'phone_number_id' => '1280262548502304',
        ];
    }
}
