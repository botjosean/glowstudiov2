<?php

namespace Tests\Feature\Assistant;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Provider;
use App\Models\Service;
use App\Support\Assistant\Coordinator;
use App\Support\Assistant\Receptionist;
use App\Support\Kapso\InboundMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Receptionist mode: two messages, then a person.
 *
 * The assertion repeated across most of these is that no request ever reaches
 * the model. That is the design, not a performance note — an assistant that
 * cannot choose its words cannot invent an appointment, ramble at somebody
 * asking about a service that is not in the catalogue, or greet a client who
 * is already on her way.
 */
class ReceptionistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.api_key' => 'test-api-key',
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.assistant.api_key' => 'test-key',
            'services.assistant.base_url' => 'https://openrouter.ai/api/v1',
        ]);

        // The model is stubbed and reachable on purpose: every receptionist
        // test below asserts it was never called, which only means anything if
        // calling it would have worked.
        Http::fake([
            'api.kapso.ai/*' => Http::response(['data' => []]),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'Respuesta del modelo']]]]),
        ]);
    }

    public function test_the_first_message_welcomes_and_promises_a_person(): void
    {
        $provider = $this->provider();

        $reply = $this->reply($provider, 'hola, cuánto cuesta un flequillo?');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Glow Studio', $reply);
        $this->assertStringContainsString('Patricia moreno', $reply);
        $this->assertStringContainsString('Recibí tu mensaje', $reply);
        $this->assertNoModelWasConsulted();
    }

    /**
     * The "flequillo" failure, which is what started this: a word the
     * catalogue does not have used to send the assistant off improvising.
     * A receptionist cannot, because it never reads the catalogue.
     */
    public function test_it_never_quotes_a_price_even_when_asked_directly(): void
    {
        $provider = $this->provider();
        Service::factory()->for($provider)->create(['name' => 'Balayage', 'price' => 200]);

        $reply = $this->reply($provider, '¿cuánto vale el balayage?');

        $this->assertNotNull($reply);
        $this->assertStringNotContainsString('200', $reply);
        $this->assertStringNotContainsString('Balayage', $reply);
        $this->assertNoModelWasConsulted();
    }

    public function test_the_second_message_asks_for_the_photos_the_name_and_the_date(): void
    {
        $provider = $this->provider();

        $this->reply($provider, 'hola');
        $second = $this->reply($provider, 'quiero hacerme algo en el cabello');

        $this->assertNotNull($second);
        $this->assertStringContainsString('foto', $second);
        $this->assertStringContainsString('tu nombre', $second);
        $this->assertStringContainsString('qué día', $second);
        $this->assertNoModelWasConsulted();
    }

    public function test_it_goes_quiet_after_two_messages(): void
    {
        $provider = $this->provider();

        $this->assertNotNull($this->reply($provider, 'hola'));
        $this->assertNotNull($this->reply($provider, 'quiero una cita'));

        $this->assertNull($this->reply($provider, '¿hay alguien?'));
        $this->assertNull($this->reply($provider, '¿hola?'));

        $this->assertSame(2, Lead::query()->sole()->bot_messages_sent);
    }

    /**
     * The "voy en camino" failure. A client who is already booked writing
     * anything at all belongs to the professional — a welcome would read to
     * her as having reached the wrong number, which is exactly what happened.
     */
    public function test_it_stays_silent_for_a_client_who_already_has_an_appointment(): void
    {
        $provider = $this->provider();

        Appointment::factory()->for($provider)->confirmed()->create([
            'client_phone' => '2056455856',
            'starts_at' => CarbonImmutable::now()->addHours(2),
            'ends_at' => CarbonImmutable::now()->addHours(3),
        ]);

        $this->assertNull($this->reply($provider, 'Pati voy en camino'));

        // And nothing was recorded either: she is not a request, she is a
        // client with an appointment.
        $this->assertSame(0, Lead::query()->count());
        $this->assertNoModelWasConsulted();
    }

    /**
     * A past appointment must not silence her: that client is free to be a
     * new enquiry again, and the guard is about who is *waiting*, not about
     * who has ever booked.
     */
    public function test_a_finished_appointment_does_not_silence_the_receptionist(): void
    {
        $provider = $this->provider();

        Appointment::factory()->for($provider)->confirmed()->create([
            'client_name' => 'María López',
            'client_phone' => '2056455856',
            'starts_at' => CarbonImmutable::now()->subDays(10),
            'ends_at' => CarbonImmutable::now()->subDays(10)->addHour(),
        ]);

        $this->assertNotNull($this->reply($provider, 'hola de nuevo'));
    }

    public function test_a_returning_client_is_greeted_by_her_first_name(): void
    {
        $provider = $this->provider();
        Client::factory()->for($provider)->create(['name' => 'María López', 'phone' => '2056455856']);

        $reply = $this->reply($provider, 'hola');

        $this->assertStringContainsString('María', $reply);
        $this->assertStringNotContainsString('López', $reply);
        $this->assertStringNotContainsString('Bienvenida', $reply);
    }

    public function test_a_stranger_is_not_greeted_by_name(): void
    {
        $reply = $this->reply($this->provider(), 'hola');

        $this->assertStringContainsString('Bienvenida', $reply);
    }

    public function test_it_stays_silent_and_closes_the_request_when_a_person_already_answered(): void
    {
        $provider = $this->provider();

        $this->reply($provider, 'hola');

        $this->assertNull($this->reply($provider, '¿me puedes atender?', humanReplied: true));

        $lead = Lead::query()->sole();
        $this->assertSame(Lead::STATUS_HANDLED, $lead->status);
        $this->assertNotNull($lead->answered_at);
        // Still one, because the second was never sent.
        $this->assertSame(1, $lead->bot_messages_sent);
    }

    public function test_it_records_what_she_wrote_so_the_panel_can_show_it(): void
    {
        $provider = $this->provider();

        $this->reply($provider, 'quiero un balayage');
        $this->reply($provider, 'María López, para el jueves');

        $lead = Lead::query()->sole();

        $this->assertStringContainsString('quiero un balayage', $lead->message);
        $this->assertStringContainsString('María López, para el jueves', $lead->message);
        $this->assertSame(Lead::STATUS_NEW, $lead->status);
    }

    public function test_a_conversation_that_went_cold_gets_its_two_messages_again(): void
    {
        $provider = $this->provider();

        $this->reply($provider, 'hola');
        $this->reply($provider, 'gracias');
        $this->assertNull($this->reply($provider, '¿hola?'));

        Lead::query()->sole()->forceFill([
            'last_contact_at' => CarbonImmutable::now()->subDays(30),
        ])->save();

        $reply = $this->reply($provider, 'hola, quiero una cita');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Recibí tu mensaje', $reply);
        // The card came back to the waiting list rather than a second one
        // being opened for the same phone.
        $this->assertSame(1, Lead::query()->count());
    }

    public function test_the_second_message_offers_the_booking_link_when_allowed(): void
    {
        $provider = $this->provider();

        $this->reply($provider, 'hola');
        $second = $this->reply($provider, 'quiero cita');

        $this->assertStringContainsString('/'.$provider->slug, $second);
    }

    public function test_the_booking_link_can_be_switched_off(): void
    {
        $provider = $this->provider(['bot_offers_booking_link' => false]);

        $this->reply($provider, 'hola');
        $second = $this->reply($provider, 'quiero cita');

        $this->assertStringNotContainsString('/'.$provider->slug, $second);
    }

    public function test_the_wording_and_the_business_identity_come_from_the_provider(): void
    {
        $provider = $this->provider([
            'bot_business_name' => 'Nails by Vane',
            'bot_greeting' => 'Hola, soy :negocio. :profesional te responde en breve.',
        ]);

        $reply = $this->reply($provider, 'hola');

        $this->assertSame('Hola, soy Nails by Vane. Patricia moreno te responde en breve.', $reply);
    }

    /**
     * Her profile can read "Patricia Moreno" while every client knows her as
     * "Pati", and the name on the client's phone should be the one she
     * answers to.
     */
    public function test_the_assistant_calls_her_what_she_asked_to_be_called(): void
    {
        $provider = $this->provider(['bot_display_name' => 'Pati']);

        $reply = $this->reply($provider, 'hola');

        $this->assertStringContainsString('Pati', $reply);
        $this->assertStringNotContainsString('Patricia moreno', $reply);
    }

    /**
     * An earlier version introduced the trade ("Soy el WhatsApp de Patricia,
     * peluquera") and the owner rejected it outright: a name and nothing else.
     */
    public function test_the_greeting_never_announces_a_trade(): void
    {
        $reply = $this->reply($this->provider(), 'hola');

        foreach (['peluquera', 'barbero', 'manicurista', 'estilista'] as $trade) {
            $this->assertStringNotContainsString($trade, mb_strtolower($reply));
        }
    }

    /**
     * An agent-mode provider must behave exactly as it did before any of this
     * existed — that is what makes the migration safe to deploy.
     */
    public function test_an_agent_provider_still_goes_through_the_model(): void
    {
        $provider = Provider::factory()->published()->create([
            'public_name' => 'Patricia moreno',
            'whatsapp_phone_number_id' => '868324373028256',
        ]);

        $reply = app(Coordinator::class)->reply($this->message('hola'), $provider);

        $this->assertSame('Respuesta del modelo', $reply);
        $this->assertSame(0, Lead::query()->count());
    }

    // ---- Adversarial ------------------------------------------------------

    /**
     * The one that bit. A Kapso send failure makes the queue retry the whole
     * job, and the count had already moved — so the client was never greeted
     * and got the intake instead, or, after two failures, nothing ever again.
     */
    public function test_a_retried_delivery_repeats_the_message_instead_of_advancing(): void
    {
        $provider = $this->provider();
        $message = $this->message('hola');

        $first = app(Receptionist::class)->reply($message, $provider, false);
        // Same inbound message, as the queue replays it after a failed send.
        $again = app(Receptionist::class)->reply($message, $provider, false);

        $this->assertSame($first, $again);
        $this->assertStringContainsString('Recibí tu mensaje', $again);
        $this->assertSame(1, Lead::query()->sole()->bot_messages_sent);

        // And the next genuinely new message still gets the second one.
        $this->assertStringContainsString('foto', $this->reply($provider, 'quiero cita'));
    }

    public function test_a_retry_does_not_print_what_she_wrote_twice_on_the_card(): void
    {
        $provider = $this->provider();
        $message = $this->message('quiero un balayage');

        app(Receptionist::class)->reply($message, $provider, false);
        app(Receptionist::class)->reply($message, $provider, false);

        $this->assertSame(1, substr_count(Lead::query()->sole()->message, 'quiero un balayage'));
    }

    /**
     * The last message is retried too, and going quiet there would swallow the
     * intake entirely.
     */
    public function test_a_retry_of_the_second_message_still_re_sends_it(): void
    {
        $provider = $this->provider();
        $this->reply($provider, 'hola');
        $second = $this->message('quiero cita');

        $sent = app(Receptionist::class)->reply($second, $provider, false);
        $retried = app(Receptionist::class)->reply($second, $provider, false);

        $this->assertSame($sent, $retried);
        $this->assertStringContainsString('foto', $retried);
        $this->assertSame(2, Lead::query()->sole()->bot_messages_sent);
    }

    /**
     * A client card is written by whoever books, so its name is not entirely
     * trusted input. A name carrying a placeholder must land as text.
     */
    public function test_a_client_name_cannot_inject_a_placeholder(): void
    {
        $provider = $this->provider(['bot_business_name' => 'Glow Studio']);
        Client::factory()->for($provider)->create(['name' => ':negocio :enlace', 'phone' => '2056455856']);

        $reply = $this->reply($provider, 'hola');

        $this->assertStringNotContainsString('Glow Studio', $reply);
        $this->assertStringNotContainsString('http', $reply);
    }

    /**
     * What the client types is never a template either.
     */
    public function test_what_the_client_writes_is_never_expanded(): void
    {
        $provider = $this->provider();

        $this->reply($provider, 'hola :profesional :enlace');
        $second = $this->reply($provider, ':negocio');

        $this->assertSame(1, substr_count($second, 'http'));
    }

    public function test_two_professionals_never_share_a_conversation(): void
    {
        $pati = $this->provider(['slug' => 'pati']);
        // A different number, because one is what routes every inbound message
        // — the database refuses to let two providers share it, which is the
        // protection this test relies on rather than exercises.
        $vane = $this->provider([
            'slug' => 'vane',
            'public_name' => 'Vanessa',
            'whatsapp_phone_number_id' => '1208335042365152',
        ]);

        // The same client writing to both is two separate conversations.
        $this->reply($pati, 'hola');
        $this->reply($pati, 'quiero cita');
        $this->assertNull($this->reply($pati, 'hola?'));

        $forVane = $this->reply($vane, 'hola');

        $this->assertNotNull($forVane);
        $this->assertStringContainsString('Vanessa', $forVane);
        $this->assertSame(2, Lead::query()->count());
    }

    /**
     * An appointment that has just started still counts: she is in the chair,
     * and a welcome would be worse there than anywhere.
     */
    public function test_an_appointment_starting_right_now_still_silences_it(): void
    {
        $provider = $this->provider();

        Appointment::factory()->for($provider)->confirmed()->create([
            'client_phone' => '2056455856',
            'starts_at' => CarbonImmutable::now()->addSeconds(30),
            'ends_at' => CarbonImmutable::now()->addHour(),
        ]);

        $this->assertNull($this->reply($provider, 'ya llegué'));
    }

    /**
     * A cancelled appointment is not a reason to stay quiet — she may well be
     * writing to book another one.
     */
    public function test_a_cancelled_appointment_does_not_silence_it(): void
    {
        $provider = $this->provider();

        Appointment::factory()->for($provider)->cancelled()->create([
            'client_phone' => '2056455856',
            'starts_at' => CarbonImmutable::now()->addDay(),
            'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        ]);

        $this->assertNotNull($this->reply($provider, 'hola, quiero reagendar'));
    }

    /**
     * @param  string  $text  the kind of thing people actually send
     */
    #[DataProvider('awkwardMessages')]
    public function test_it_answers_whatever_she_types(string $text): void
    {
        $reply = $this->reply($this->provider(), $text);

        $this->assertNotNull($reply);
        $this->assertNoModelWasConsulted();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function awkwardMessages(): array
    {
        return [
            'solo emoji' => ['💅💅💅'],
            'grito' => ['HOLAAAAA BUENAS TARDESSS'],
            'sin acentos ni signos' => ['ola kiero saber cuanto sale el balayage y si tenes lugar manana'],
            'un solo caracter' => ['?'],
            'muy largo' => [str_repeat('hola necesito una cita porfa ', 200)],
            'ingles' => ['hi do you have any openings tomorrow'],
            'incoherente' => ['asdkjh 123 ??? ..... !!!'],
            'con salto de linea' => ["hola\n\nquiero\n\ncita"],
        ];
    }

    private function assertNoModelWasConsulted(): void
    {
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'openrouter.ai'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function provider(array $attributes = []): Provider
    {
        return Provider::factory()->published()->receptionist()->create([
            'public_name' => 'Patricia moreno',
            'timezone' => 'America/New_York',
            'whatsapp_phone_number_id' => '868324373028256',
            ...$attributes,
        ]);
    }

    private function reply(Provider $provider, string $text, bool $humanReplied = false): ?string
    {
        return app(Receptionist::class)->reply($this->message($text), $provider, $humanReplied);
    }

    private function message(string $text): InboundMessage
    {
        $message = InboundMessage::fromDelivery([
            'message' => [
                'id' => 'wamid.'.bin2hex(random_bytes(4)),
                'type' => 'text',
                'from' => '12056455856',
                'text' => ['body' => $text],
                'kapso' => ['direction' => 'inbound', 'origin' => 'cloud_api'],
            ],
            'conversation' => [
                'id' => 'conv_1',
                'phone_number' => '12056455856',
                'phone_number_id' => '868324373028256',
            ],
            'phone_number_id' => '868324373028256',
        ]);

        $this->assertNotNull($message);

        return $message;
    }
}
