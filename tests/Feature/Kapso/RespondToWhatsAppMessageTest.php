<?php

namespace Tests\Feature\Kapso;

use App\Jobs\RespondToWhatsAppMessage;
use App\Models\Provider;
use App\Notifications\HumanHandoffRequested;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\OptOutRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RespondToWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_NUMBER_ID = '868324373028256';

    private const CLIENT = '12056455856';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.api_key' => 'test-api-key',
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.kapso.graph_version' => 'v24.0',
            // Who may be answered is ReplyPolicy's concern; most cases here are
            // about what happens once someone may be.
            'services.kapso.reply_mode' => 'everyone',
            'services.assistant.api_key' => 'test-groq-key',
            'services.assistant.base_url' => 'https://openrouter.ai/api/v1',
            'services.assistant.model' => 'openai/gpt-oss-120b',
            'services.assistant.max_iterations' => 3,
        ]);
    }

    public function test_it_sends_what_the_assistant_answered_through_the_number_it_arrived_on(): void
    {
        $this->fake($this->text('¡Hola! ¿Qué te gustaría reservar?'));
        $this->provider();

        $this->runJob($this->job());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.kapso.ai/meta/whatsapp/v24.0/'.self::PHONE_NUMBER_ID.'/messages'
                && $request['to'] === self::CLIENT
                && $request['text']['body'] === '¡Hola! ¿Qué te gustaría reservar?';
        });
    }

    public function test_it_sends_exactly_one_reply_per_inbound_message(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        $job = $this->job();
        $this->runJob($job);
        $this->runJob($job);

        $this->assertSame(1, $this->sentMessages());
    }

    public function test_a_number_outside_the_allowlist_is_never_answered(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '14044518022',
        ]);

        $this->runJob($this->job());

        $this->assertSame(0, $this->sentMessages());
    }

    public function test_an_unset_reply_configuration_answers_nobody(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        config([
            'services.kapso.reply_mode' => null,
            'services.kapso.test_recipients' => null,
        ]);

        $this->runJob($this->job());

        $this->assertSame(0, $this->sentMessages());
    }

    /**
     * Vanessa's guard: a number still shared with personal use must not
     * answer anyone already saved in her phone contacts. Confirmed against a
     * live payload that Kapso never puts contact_name in the webhook
     * delivery itself (not under message.kapso, not under conversation.kapso
     * — contrary to the webhook docs' example), so the job asks for it with
     * a dedicated lookup instead of trusting the delivery.
     */
    public function test_a_saved_contact_is_not_answered_on_a_guarded_number(): void
    {
        $this->fakeWithContactLookup(['kapso' => ['contact_name' => 'Josean Sosa']]);
        $this->provider();

        config(['services.kapso.personal_phone_number_id' => self::PHONE_NUMBER_ID]);

        $this->runJob($this->job());

        $this->assertSame(0, $this->sentMessages());
    }

    /**
     * The owner's decision of 2026-08-16: a receptionist's two short messages
     * are welcome even on a number shared with personal use, because it does
     * not chat — "un msj automático no molesta, antes molestaba porque
     * chateaba de forma normal". So the guard follows the mode, not the
     * number.
     */
    public function test_a_saved_contact_is_answered_when_that_number_is_a_receptionist(): void
    {
        $this->fake($this->text('irrelevante: no se consulta el modelo'));
        $this->provider()->update(['bot_mode' => Provider::BOT_RECEPTIONIST]);

        config(['services.kapso.personal_phone_number_id' => self::PHONE_NUMBER_ID]);

        $this->runJob($this->job());

        $this->assertSame(1, $this->sentMessages());

        // And it did not spend a Kapso round trip resolving a contact name it
        // no longer needs.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/platform/v1/whatsapp/conversations/'));
    }

    /**
     * The other half of the guard: a stranger with no saved name is still a
     * potential new client and must be answered normally. Kapso's default
     * lookup response for someone it does not know carries no name at all.
     */
    public function test_a_stranger_with_no_saved_name_is_still_answered_on_a_guarded_number(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        config(['services.kapso.personal_phone_number_id' => self::PHONE_NUMBER_ID]);

        $this->runJob($this->job());

        $this->assertSame(1, $this->sentMessages());
    }

    /**
     * Kapso's fallback when nothing is saved: not a missing name, but the
     * sender's own phone number echoed back as one (seen on Patricia's real
     * conversations before this guard existed). That is not a real contact.
     */
    public function test_kapsos_bare_number_fallback_is_still_answered_on_a_guarded_number(): void
    {
        $this->fakeWithContactLookup(['kapso' => ['contact_name' => self::CLIENT]]);
        $this->provider();

        config(['services.kapso.personal_phone_number_id' => self::PHONE_NUMBER_ID]);

        $this->runJob($this->job());

        $this->assertSame(1, $this->sentMessages());
    }

    /**
     * An API hiccup on the lookup must not silence the assistant for a
     * genuine new client — it falls back to answering, same as a stranger.
     */
    public function test_a_failed_contact_lookup_still_answers_rather_than_staying_silent(): void
    {
        Http::fake([
            'api.kapso.ai/platform/v1/whatsapp/conversations/*' => Http::response('boom', 500),
            'api.kapso.ai/platform/*' => Http::response(['data' => []]),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'openrouter.ai/*' => Http::response($this->text('Hola')),
        ]);
        $this->provider();

        config(['services.kapso.personal_phone_number_id' => self::PHONE_NUMBER_ID]);

        // Built without a contact_name, matching the real webhook delivery
        // shape — job() carries one purely as legacy fixture data other
        // tests still rely on and would make this case test nothing.
        $message = InboundMessage::fromDelivery([
            'message' => [
                'id' => 'wamid.lookup-failure',
                'type' => 'text',
                'from' => self::CLIENT,
                'text' => ['body' => 'hola'],
                'kapso' => ['direction' => 'inbound', 'origin' => 'cloud_api'],
            ],
            'conversation' => ['id' => 'conv_1', 'phone_number' => self::CLIENT, 'phone_number_id' => self::PHONE_NUMBER_ID],
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        $this->assertNotNull($message);
        $this->runJob(new RespondToWhatsAppMessage($message));

        $this->assertSame(1, $this->sentMessages());
    }

    /**
     * The lookup is scoped to the guarded number: every other message must
     * not pay for an extra request it does not need.
     */
    public function test_the_contact_lookup_is_never_made_for_a_number_without_the_guard(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        $this->runJob($this->job());

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/whatsapp/conversations/'));
    }

    /**
     * @param  array<string, mixed>  $conversationData
     */
    private function fakeWithContactLookup(array $conversationData): void
    {
        Http::fake([
            'api.kapso.ai/platform/v1/whatsapp/conversations/*' => Http::response(['data' => $conversationData]),
            'api.kapso.ai/platform/*' => Http::response(['data' => []]),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'openrouter.ai/*' => Http::response($this->text('Hola')),
        ]);
    }

    public function test_a_message_without_a_phone_number_is_skipped_without_sending(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        $message = InboundMessage::fromDelivery([
            'message' => [
                'id' => 'wamid.bsuid',
                'type' => 'text',
                'from_user_id' => 'US.1349',
                'text' => ['body' => 'hola'],
                'kapso' => ['direction' => 'inbound'],
            ],
            'conversation' => ['id' => 'c1', 'business_scoped_user_id' => 'US.1349', 'phone_number_id' => self::PHONE_NUMBER_ID],
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        $this->assertNotNull($message);
        $this->runJob(new RespondToWhatsAppMessage($message));

        $this->assertSame(0, $this->sentMessages());
    }

    public function test_stop_records_an_opt_out_and_confirms_it_once(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();

        $this->runJob($this->job('STOP'));

        $this->assertTrue(app(OptOutRegistry::class)->optedOut(self::PHONE_NUMBER_ID, self::CLIENT));
        $this->assertSame(1, $this->sentMessages());

        Http::assertSent(fn (Request $request): bool => str_contains(
            $request['text']['body'] ?? '', 'no volveremos a escribirte'
        ));
    }

    public function test_an_opted_out_number_gets_no_further_answers(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();
        app(OptOutRegistry::class)->optOut(self::PHONE_NUMBER_ID, self::CLIENT);

        $this->runJob($this->job('quiero una cita para el viernes'));

        $this->assertSame(0, $this->sentMessages());
    }

    public function test_start_brings_an_opted_out_number_back(): void
    {
        $this->fake($this->text('Hola'));
        $this->provider();
        app(OptOutRegistry::class)->optOut(self::PHONE_NUMBER_ID, self::CLIENT);

        $this->runJob($this->job('START'));

        $this->assertFalse(app(OptOutRegistry::class)->optedOut(self::PHONE_NUMBER_ID, self::CLIENT));
        $this->assertSame(1, $this->sentMessages());
    }

    /**
     * The trap worth a test of its own: a client cancelling an appointment must
     * not be silently unsubscribed from the salon.
     */
    public function test_wanting_to_cancel_an_appointment_is_not_an_opt_out(): void
    {
        $this->fake($this->text('Claro, ¿cuál cita quieres cancelar?'));
        $this->provider();

        $this->runJob($this->job('quiero cancelar mi cita'));

        $this->assertFalse(app(OptOutRegistry::class)->optedOut(self::PHONE_NUMBER_ID, self::CLIENT));
        Http::assertSent(fn (Request $request): bool => ($request['text']['body'] ?? '') === 'Claro, ¿cuál cita quieres cancelar?');
    }

    /**
     * A number nobody owns has no catalogue, no schedule and nobody to hand off
     * to — but the client still deserves an answer rather than silence.
     */
    public function test_an_unmapped_number_answers_with_the_waiting_message(): void
    {
        $this->fake($this->text('Hola'));
        // Deliberately no provider carrying this phone_number_id.

        $this->runJob($this->job());

        Http::assertSent(fn (Request $request): bool => str_contains(
            $request['text']['body'] ?? '', 'una persona te contesta en breve'
        ));
    }

    public function test_an_assistant_failure_hands_off_to_a_person_and_still_answers_the_client(): void
    {
        Notification::fake();

        Http::fake([
            'api.kapso.ai/platform/*' => Http::response(['data' => []]),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            // A 400 is terminal, so the job must not sit on retries.
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'failed_generation']], 400),
        ]);

        $provider = $this->provider();

        $this->runJob($this->job());

        Http::assertSent(fn (Request $request): bool => str_contains(
            $request['text']['body'] ?? '', 'una persona te contesta en breve'
        ));

        Notification::assertSentTo($provider->user, HumanHandoffRequested::class);
    }

    /**
     * A rate limit deserves a retry, not a hand-off — so nothing is sent yet.
     */
    public function test_a_rate_limit_defers_instead_of_answering(): void
    {
        Notification::fake();

        Http::fake([
            'api.kapso.ai/platform/*' => Http::response(['data' => []]),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'slow down']], 429, ['retry-after' => '12']),
        ]);

        $this->provider();

        $this->runJob($this->job());

        $this->assertSame(0, $this->sentMessages());
        Notification::assertNothingSent();
    }

    private function runJob(RespondToWhatsAppMessage $job): void
    {
        // Let the container resolve the dependencies, so this keeps working if
        // the job gains another one.
        $this->app->call([$job, 'handle']);
    }

    private function job(string $text = 'hola'): RespondToWhatsAppMessage
    {
        $message = InboundMessage::fromDelivery([
            'message' => [
                'id' => 'wamid.111',
                'type' => 'text',
                'from' => self::CLIENT,
                'text' => ['body' => $text],
                'kapso' => ['direction' => 'inbound', 'origin' => 'cloud_api'],
            ],
            'conversation' => [
                'id' => 'conv_1',
                'phone_number' => self::CLIENT,
                'phone_number_id' => self::PHONE_NUMBER_ID,
                'kapso' => ['contact_name' => 'Josean Sosa'],
            ],
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        $this->assertNotNull($message);

        return new RespondToWhatsAppMessage($message);
    }

    private function provider(): Provider
    {
        return Provider::factory()->published()->create([
            'public_name' => 'Patricia moreno',
            'timezone' => 'America/New_York',
            'whatsapp_phone_number_id' => self::PHONE_NUMBER_ID,
        ]);
    }

    /**
     * @param  array<mixed>  $groqResponse
     */
    private function fake(array $groqResponse): void
    {
        Http::fake([
            'api.kapso.ai/platform/*' => Http::response(['data' => []]),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'openrouter.ai/*' => Http::response($groqResponse),
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function text(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    private function sentMessages(): int
    {
        $sent = 0;

        Http::recorded(function (Request $request) use (&$sent): void {
            if (str_contains($request->url(), '/meta/whatsapp/')) {
                $sent++;
            }
        });

        return $sent;
    }
}
