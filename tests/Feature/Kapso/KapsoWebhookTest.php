<?php

namespace Tests\Feature\Kapso;

use App\Jobs\RespondToWhatsAppMessage;
use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KapsoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private const ENDPOINT = '/api/kapso/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.webhook_secret' => self::SECRET,
            // These cases are about the webhook contract, not about who may be
            // answered; ReplyPolicyTest covers that. 'everyone' keeps the two
            // concerns from tangling.
            'services.kapso.reply_mode' => 'everyone',
        ]);

        Queue::fake();
    }

    /**
     * The connected numbers are real working business lines, so the endpoint
     * must refuse to queue anything for a number outside the allowlist — no
     * job, no claim, nothing to go wrong later.
     */
    public function test_a_number_outside_the_allowlist_is_never_queued(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '12056455856',
        ]);

        $response = $this->deliver($this->payload());

        $response->assertOk();
        $response->assertJson(['status' => 'accepted', 'queued' => 0, 'not_allowed' => 1]);

        Queue::assertNothingPushed();
    }

    public function test_a_number_inside_the_allowlist_is_queued(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            // Written the way a human would, to prove formatting is normalised.
            'services.kapso.test_recipients' => '+1 (631) 555-1181',
        ]);

        $response = $this->deliver($this->payload());

        $response->assertOk();
        $response->assertJson(['status' => 'accepted', 'queued' => 1, 'not_allowed' => 0]);

        Queue::assertPushed(RespondToWhatsAppMessage::class, 1);
    }

    /**
     * A disallowed message must not consume the message claim either, or
     * widening the allowlist later would leave that client permanently
     * unanswerable.
     */
    public function test_a_disallowed_message_claims_nothing_so_it_can_be_answered_later(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '12056455856',
        ]);

        $this->deliver($this->payload(), idempotencyKey: 'delivery-1')->assertOk();

        $this->assertFalse(
            app(WebhookDeduplicator::class)->claimed(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.111')
        );

        config(['services.kapso.test_recipients' => '16315551181']);

        $this->deliver($this->payload(), idempotencyKey: 'delivery-2')
            ->assertJson(['queued' => 1]);
    }

    public function test_a_correctly_signed_inbound_text_is_accepted_and_queued(): void
    {
        $response = $this->deliver($this->payload());

        $response->assertOk();
        $response->assertJson(['status' => 'accepted', 'queued' => 1]);

        Queue::assertPushed(RespondToWhatsAppMessage::class, function (RespondToWhatsAppMessage $job): bool {
            return $job->message->wamid === 'wamid.111'
                && $job->message->text === 'Hola, quiero una cita'
                && $job->message->fromPhone === '16315551181'
                && $job->message->phoneNumberId === '1280262548502304'
                && $job->message->contactName === 'Jane Client';
        });
    }

    public function test_an_invalid_signature_is_rejected_and_nothing_is_queued(): void
    {
        $body = json_encode($this->payload());

        $response = $this->deliverRaw($body, str_repeat('a', 64));

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $body = json_encode($this->payload());

        $response = $this->call('POST', self::ENDPOINT, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_EVENT' => 'whatsapp.message.received',
        ], $body);

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    /**
     * Kapso's documented samples sign a re-serialisation of the parsed body
     * rather than the bytes received, so a signature that only matches that
     * form must still be accepted — otherwise every real delivery 401s.
     */
    public function test_a_signature_over_the_reserialized_body_is_accepted(): void
    {
        $payload = $this->payload();

        // Whitespace makes the transmitted bytes differ from the canonical
        // re-encoding, so only the re-serialised candidate can match.
        $body = json_encode($payload, JSON_PRETTY_PRINT);
        $signature = hash_hmac(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            self::SECRET
        );

        $this->deliverRaw($body, $signature)->assertOk();

        Queue::assertPushed(RespondToWhatsAppMessage::class);
    }

    public function test_a_signature_carrying_metas_sha256_prefix_is_accepted(): void
    {
        $body = json_encode($this->payload());
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        $this->deliverRaw($body, $signature)->assertOk();

        Queue::assertPushed(RespondToWhatsAppMessage::class);
    }

    public function test_a_repeated_idempotency_key_is_acknowledged_without_reprocessing(): void
    {
        $this->deliver($this->payload(), idempotencyKey: 'delivery-1')->assertOk();

        $response = $this->deliver($this->payload(), idempotencyKey: 'delivery-1');

        $response->assertOk();
        $response->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(RespondToWhatsAppMessage::class, 1);
    }

    /**
     * After its retries are exhausted Kapso re-sends a batch as individual
     * deliveries, each with a fresh idempotency key but the same messages —
     * so the per-message claim, not the per-delivery one, is what stops a
     * second reply here.
     */
    public function test_a_message_already_seen_under_another_delivery_is_not_queued_twice(): void
    {
        $this->deliver($this->payload(), idempotencyKey: 'delivery-1')->assertOk();

        $response = $this->deliver($this->payload(), idempotencyKey: 'delivery-2');

        $response->assertOk();
        $response->assertJson(['status' => 'accepted', 'queued' => 0]);

        Queue::assertPushed(RespondToWhatsAppMessage::class, 1);
    }

    /**
     * A client firing off three lines in ten seconds was getting three separate
     * replies that talked over each other. Buffered together, they are one thing
     * to answer.
     */
    public function test_a_batch_from_one_conversation_is_answered_once(): void
    {
        $payload = [
            'type' => 'whatsapp.message.received',
            'batch' => true,
            'data' => [
                $this->payload(wamid: 'wamid.aaa', text: 'Primero'),
                $this->payload(wamid: 'wamid.bbb', text: 'Segundo'),
            ],
            'batch_info' => ['size' => 2, 'window_ms' => 5000],
        ];

        $response = $this->deliver($payload, batch: true);

        $response->assertOk();
        $response->assertJson(['status' => 'accepted', 'queued' => 1]);

        Queue::assertPushed(RespondToWhatsAppMessage::class, 1);

        Queue::assertPushed(RespondToWhatsAppMessage::class, function (RespondToWhatsAppMessage $job): bool {
            return $job->message->text === "Primero\nSegundo"
                // Keyed on the last message: that is the one the client is
                // waiting on, and the one the reply claim must cover.
                && $job->message->wamid === 'wamid.bbb'
                // Both still listed, so neither is replayed as history whose
                // text is already in the merged subject.
                && $job->message->wamids === ['wamid.aaa', 'wamid.bbb'];
        });
    }

    /**
     * Merging is per conversation, not per delivery: two different people in one
     * batch are two different problems.
     */
    public function test_a_batch_from_two_conversations_is_answered_once_each(): void
    {
        $payload = [
            'type' => 'whatsapp.message.received',
            'batch' => true,
            'data' => [
                $this->payload(wamid: 'wamid.aaa', text: 'Soy una'),
                $this->payload(wamid: 'wamid.bbb', text: 'Soy otra', from: '14045550000', conversationId: 'conv_otra'),
            ],
            'batch_info' => ['size' => 2],
        ];

        $response = $this->deliver($payload, batch: true);

        $response->assertOk();
        $response->assertJson(['status' => 'accepted', 'queued' => 2]);

        Queue::assertPushed(RespondToWhatsAppMessage::class, 2);
    }

    /**
     * Every message of a batch is claimed even though one answer goes out, so a
     * later individual re-delivery of one of them cannot resurrect it alone.
     */
    public function test_every_message_of_a_batch_is_claimed(): void
    {
        $payload = [
            'type' => 'whatsapp.message.received',
            'batch' => true,
            'data' => [
                $this->payload(wamid: 'wamid.aaa', text: 'Primero'),
                $this->payload(wamid: 'wamid.bbb', text: 'Segundo'),
            ],
        ];

        $this->deliver($payload, batch: true)->assertOk();

        $deduplicator = app(WebhookDeduplicator::class);
        $this->assertTrue($deduplicator->claimed(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.aaa'));
        $this->assertTrue($deduplicator->claimed(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.bbb'));

        // And the earlier one alone is not answered again.
        $this->deliver($this->payload(wamid: 'wamid.aaa', text: 'Primero'))
            ->assertJson(['queued' => 0]);
    }

    /**
     * With buffering enabled every delivery is wrapped in a batch envelope,
     * including one holding a single message — the header is what says so.
     */
    public function test_a_single_message_batch_is_still_read_as_a_batch(): void
    {
        $payload = [
            'type' => 'whatsapp.message.received',
            'batch' => true,
            'data' => [$this->payload()],
            'batch_info' => ['size' => 1],
        ];

        $this->deliver($payload, batch: true)->assertOk();

        Queue::assertPushed(RespondToWhatsAppMessage::class, 1);
    }

    public function test_an_outbound_message_is_ignored(): void
    {
        $payload = $this->payload();
        $payload['message']['kapso']['direction'] = 'outbound';

        $response = $this->deliver($payload);

        $response->assertOk();
        $response->assertJson(['queued' => 0]);
        Queue::assertNothingPushed();
    }

    public function test_an_echo_of_our_own_message_is_ignored(): void
    {
        $payload = $this->payload();
        $payload['message']['kapso']['origin'] = 'smb_message_echo';

        $this->deliver($payload)->assertJson(['queued' => 0]);

        Queue::assertNothingPushed();
    }

    public function test_a_non_text_message_is_ignored(): void
    {
        $payload = $this->payload();
        $payload['message']['type'] = 'image';
        unset($payload['message']['text']);

        $this->deliver($payload)->assertJson(['queued' => 0]);

        Queue::assertNothingPushed();
    }

    public function test_an_unsubscribed_event_is_acknowledged_and_dropped(): void
    {
        $response = $this->deliver($this->payload(), event: 'whatsapp.message.status_updated');

        $response->assertOk();
        $response->assertJson(['status' => 'ignored']);
        Queue::assertNothingPushed();
    }

    /**
     * A rejected delivery must leave no trace, or a later legitimate retry of
     * the same message would be dismissed as a duplicate.
     */
    public function test_a_rejected_delivery_claims_nothing(): void
    {
        $this->deliverRaw(json_encode($this->payload()), str_repeat('b', 64), idempotencyKey: 'delivery-1')
            ->assertUnauthorized();

        $this->assertFalse(
            app(WebhookDeduplicator::class)->claimed(WebhookDeduplicator::SCOPE_DELIVERY, 'delivery-1')
        );
    }

    /**
     * Every answered message spends model tokens and the sender chooses how many
     * to send, so without a ceiling one person hammering the number is an
     * unbounded bill.
     */
    public function test_a_conversation_past_its_hourly_allowance_is_not_answered(): void
    {
        // One under the limit, so the next message is the one that trips it.
        for ($i = 0; $i < 30; $i++) {
            $this->deliver($this->payload(wamid: "wamid.flood.{$i}"))->assertOk();
        }

        Queue::assertPushed(RespondToWhatsAppMessage::class, 30);

        $response = $this->deliver($this->payload(wamid: 'wamid.flood.over'));

        $response->assertOk();
        $response->assertJson(['throttled' => 1, 'queued' => 0]);

        // Still 30: the message over the line produced no job at all.
        Queue::assertPushed(RespondToWhatsAppMessage::class, 30);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function deliver(
        array $payload,
        string $event = 'whatsapp.message.received',
        ?string $idempotencyKey = null,
        bool $batch = false,
    ): TestResponse {
        $body = json_encode($payload);

        return $this->deliverRaw(
            $body,
            hash_hmac('sha256', $body, self::SECRET),
            $event,
            $idempotencyKey,
            $batch,
        );
    }

    private function deliverRaw(
        string $body,
        string $signature,
        string $event = 'whatsapp.message.received',
        ?string $idempotencyKey = null,
        bool $batch = false,
    ): TestResponse {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_EVENT' => $event,
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_WEBHOOK_PAYLOAD_VERSION' => 'v2',
            'HTTP_X_IDEMPOTENCY_KEY' => $idempotencyKey ?? 'delivery-'.md5($body),
        ];

        if ($batch) {
            $server['HTTP_X_WEBHOOK_BATCH'] = 'true';
        }

        return $this->call('POST', self::ENDPOINT, [], [], [], $server, $body);
    }

    /**
     * A payload v2 delivery, shaped exactly like the documented example.
     *
     * @return array<mixed>
     */
    private function payload(
        string $wamid = 'wamid.111',
        string $text = 'Hola, quiero una cita',
        string $from = '16315551181',
        string $conversationId = 'conv_123',
    ): array {
        return [
            'message' => [
                'id' => $wamid,
                'timestamp' => '1730092801',
                'type' => 'text',
                'from' => $from,
                'text' => ['body' => $text],
                'kapso' => [
                    'direction' => 'inbound',
                    'status' => 'received',
                    'origin' => 'cloud_api',
                ],
            ],
            'conversation' => [
                'id' => $conversationId,
                'phone_number' => $from,
                'status' => 'active',
                'phone_number_id' => '1280262548502304',
                'kapso' => ['contact_name' => 'Jane Client'],
            ],
            'is_new_conversation' => false,
            'phone_number_id' => '1280262548502304',
        ];
    }
}
