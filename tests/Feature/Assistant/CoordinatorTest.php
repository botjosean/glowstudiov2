<?php

namespace Tests\Feature\Assistant;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Support\Assistant\AssistantUnavailable;
use App\Support\Assistant\Coordinator;
use App\Support\Kapso\InboundMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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

            // Anchored to the heading on its own line, not to the word
            // anywhere: negocio.md is the owner's file and already refers to
            // "la sección UBICACIÓN" in prose, which quietly turned this
            // assertion red without anything in the prompt being wrong.
            return preg_match('/^UBICACIÓN$/mu', $request['messages'][0]['content']) === 0;
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

    /**
     * The real failure, reproduced: the model announced a booking twice in a
     * real conversation (2026-08-12) without ever calling crear_cita. The
     * guardrail must refuse to relay that text and push the model to either
     * actually book or ask for what is missing, instead of lying to the
     * client for free.
     */
    public function test_it_refuses_to_relay_a_confirmation_the_model_never_booked(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['name' => 'Corte', 'price' => 45, 'duration_minutes' => 45]);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));

        $this->fakeGroq([
            $this->text('¡Listo! Cita agendada para el martes a las 10:00 AM.'),
            $this->toolCall('crear_cita', json_encode([
                'servicio_id' => $service->id,
                'fecha' => '2026-08-11',
                'hora' => '10:00',
                'nombre_completo' => 'Jose Sosa',
            ])),
            $this->text('¡Listo! Cita agendada para el martes a las 10:00 AM.'),
        ]);

        $reply = $this->coordinator()->reply($this->message('agendame el corte el martes a las 10am, soy jose sosa'), $provider);

        $this->assertSame('¡Listo! Cita agendada para el martes a las 10:00 AM.', $reply);
        $this->assertDatabaseCount('appointments', 1);

        // Three model calls, not two: the hallucinated first answer must have
        // been rejected and re-prompted rather than relayed straight through.
        $this->assertCount(3, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')));

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'chat/completions')) {
                return false;
            }

            $roles = array_column($request['messages'], 'role');

            return in_array('system', $roles, true)
                && str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE) ?: '', 'no puedes decirle que quedo agendada');
        });

        $this->travelBack();
    }

    /**
     * The other side of the same guardrail: once crear_cita really did
     * succeed, the model must be free to confirm it in plain declarative
     * text without being forced into another round.
     */
    public function test_a_genuine_confirmation_after_a_real_booking_is_relayed_normally(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['name' => 'Corte', 'price' => 45, 'duration_minutes' => 45]);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));

        $this->fakeGroq([
            $this->toolCall('crear_cita', json_encode([
                'servicio_id' => $service->id,
                'fecha' => '2026-08-11',
                'hora' => '10:00',
                'nombre_completo' => 'Jose Sosa',
            ])),
            $this->text('¡Listo! Cita agendada para el martes a las 10:00 AM.'),
        ]);

        $reply = $this->coordinator()->reply($this->message('agendame el corte el martes a las 10am, soy jose sosa'), $provider);

        $this->assertSame('¡Listo! Cita agendada para el martes a las 10:00 AM.', $reply);
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')));

        $this->travelBack();
    }

    /**
     * The exact wording a live dry run of this guardrail let through: past
     * tense ("quedó"), not the present tense ("queda") the first version of
     * the regex only checked for.
     */
    public function test_it_catches_the_past_tense_phrasing_a_live_run_missed(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['name' => 'Corte', 'price' => 45, 'duration_minutes' => 45]);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));

        $this->fakeGroq([
            $this->text('De nada, Jose. La cita quedó pendiente de confirmación por parte del salón.'),
            $this->toolCall('crear_cita', json_encode([
                'servicio_id' => $service->id,
                'fecha' => '2026-08-11',
                'hora' => '10:00',
                'nombre_completo' => 'Jose Sosa',
            ])),
            $this->text('¡Listo! Quedó agendada para las 10:00 AM.'),
        ]);

        $reply = $this->coordinator()->reply($this->message('si correcto gracias'), $provider);

        $this->assertSame('¡Listo! Quedó agendada para las 10:00 AM.', $reply);
        $this->assertDatabaseCount('appointments', 1);

        $this->travelBack();
    }

    /**
     * Two more real phrasings a 6-client live benchmark surfaced in the same
     * session: "está pendiente de confirmación" and "tiene una cita ...
     * pendiente de confirmación" — different verbs than the first fix
     * covered, same underlying claim.
     *
     * @return list<array{0: string}>
     */
    public static function unbookedConfirmationPhrasingsProvider(): array
    {
        return [
            ['Tu cita para un corte el martes que viene a las 11 AM está pendiente de confirmación por parte del salón.'],
            ['Tu sobrino Joseito Sosa tiene una cita para corte clasico el martes que viene a las 11:45 AM, pendiente de confirmacion.'],
        ];
    }

    #[DataProvider('unbookedConfirmationPhrasingsProvider')]
    public function test_it_catches_other_real_phrasings_a_live_run_surfaced(string $hallucinated): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['name' => 'Corte', 'price' => 45, 'duration_minutes' => 45]);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));

        $this->fakeGroq([
            $this->text($hallucinated),
            $this->toolCall('crear_cita', json_encode([
                'servicio_id' => $service->id,
                'fecha' => '2026-08-11',
                'hora' => '10:00',
                'nombre_completo' => 'Jose Sosa',
            ])),
            $this->text('¡Listo! Quedó agendada para las 10:00 AM.'),
        ]);

        $reply = $this->coordinator()->reply($this->message('si correcto gracias'), $provider);

        $this->assertSame('¡Listo! Quedó agendada para las 10:00 AM.', $reply);
        $this->assertDatabaseCount('appointments', 1);

        $this->travelBack();
    }

    /**
     * A proposal awaiting the client's yes must never be mistaken for a
     * completed booking — that would let the guardrail itself pressure the
     * model into calling crear_cita before the client actually agreed.
     */
    public function test_a_proposal_with_a_question_is_never_treated_as_a_confirmation(): void
    {
        $this->fakeGroq([
            $this->text('Te agendo un corte el martes a las 10:00 AM. ¿Es correcto?'),
        ]);

        $reply = $this->coordinator()->reply($this->message('quiero un corte el martes a las 10am'), $this->provider());

        $this->assertSame('Te agendo un corte el martes a las 10:00 AM. ¿Es correcto?', $reply);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')));
    }

    /**
     * WhatsApp renders one asterisk as bold and shows two literally, so
     * Markdown from the model reaches the client as visible punctuation.
     * Caught on `claude-haiku-4.5` minutes after it went live, in two of its
     * first three answers, despite the prompt asking for one asterisk.
     */
    public function test_markdown_bold_is_rewritten_as_whatsapp_bold(): void
    {
        $this->fakeGroq([$this->text('El martes que viene es **18 de agosto** ☺️')]);

        $reply = $this->coordinator()->reply($this->message('que fecha es el martes'), $this->provider());

        $this->assertSame('El martes que viene es *18 de agosto* ☺️', $reply);
    }

    /**
     * A lone pair of asterisks around nothing, and the ordinary single-asterisk
     * form, both have to survive untouched — a rewrite that mangles correct
     * text is worse than the Markdown it was meant to fix.
     */
    public function test_it_leaves_text_that_was_already_right_alone(): void
    {
        $this->fakeGroq([$this->text('Atendemos *todos los días* de 10:00 AM a 9:00 PM. Precio: ** consultar')]);

        $reply = $this->coordinator()->reply($this->message('horarios'), $this->provider());

        $this->assertSame('Atendemos *todos los días* de 10:00 AM a 9:00 PM. Precio: ** consultar', $reply);
    }

    /**
     * The regression that made this guard ask the database instead of the
     * wording.
     *
     * A client asking after an appointment she already has gets a true answer
     * built from listar_mis_citas or from the prompt's own CLIENTA CONOCIDA
     * block — crear_cita never runs, and never should. The first version of
     * the guard blocked exactly these four sentences, which is the single
     * most ordinary thing a client writes after booking.
     *
     * @return list<array{0: string}>
     */
    public static function trueStatementsAboutAnExistingAppointmentProvider(): array
    {
        return [
            ['Sí bella, tu cita de Corte está confirmada para el martes a las 10:00 AM. Te esperamos 💛'],
            ['Tienes una cita agendada el martes a las 10:00 AM con Patricia moreno.'],
            ['Veo tu cita registrada para el martes a las 10:00 AM.'],
            ['Tu cita sigue pendiente de confirmación por parte del salón, bella.'],
        ];
    }

    #[DataProvider('trueStatementsAboutAnExistingAppointmentProvider')]
    public function test_a_true_statement_about_an_appointment_she_really_has_is_relayed(string $answer): void
    {
        $provider = $this->provider();

        Appointment::factory()->for($provider)->create([
            'client_phone' => '2056455856',
            'starts_at' => CarbonImmutable::now()->addDays(2)->setTime(10, 0),
            'ends_at' => CarbonImmutable::now()->addDays(2)->setTime(10, 40),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->fakeGroq([$this->text($answer)]);

        $reply = $this->coordinator()->reply($this->message('sigue en pie mi cita'), $provider);

        $this->assertSame($answer, $reply);

        // One model call: no correction round, because nothing was untrue.
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')));
    }

    /**
     * The same sentence is a lie when the appointment does not exist, and the
     * guard has to tell the two apart by the table, not by the phrasing.
     */
    public function test_the_same_wording_is_refused_when_she_has_no_appointment(): void
    {
        $this->fakeGroq([
            $this->text('Tienes una cita agendada el martes a las 10:00 AM con Patricia moreno.'),
            $this->text('Perdona, todavía no te la he agendado. ¿Te la reservo?'),
        ]);

        $reply = $this->coordinator()->reply($this->message('sigue en pie mi cita'), $this->provider());

        $this->assertSame('Perdona, todavía no te la he agendado. ¿Te la reservo?', $reply);
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')));
    }

    /**
     * The assistant is told to answer in English when written to in English,
     * so a Spanish-only guard is a guard with the lights off half the time.
     */
    public function test_a_fabricated_booking_in_english_is_refused_too(): void
    {
        $this->fakeGroq([
            $this->text('All set! Your appointment is booked for Tuesday at 10:00 AM.'),
            $this->text('Sorry, I have not booked it yet. Shall I?'),
        ]);

        $reply = $this->coordinator()->reply($this->message('book me a haircut tuesday 10am'), $this->provider());

        $this->assertSame('Sorry, I have not booked it yet. Shall I?', $reply);
    }

    /**
     * A single question mark used to exempt the whole message, and the model's
     * habit is to close with one — so the exact shape that started this
     * incident ("¡Listo! ... ¿Necesitas algo más?") walked straight through.
     * Judged sentence by sentence now.
     */
    public function test_a_fabricated_booking_is_caught_even_when_the_message_ends_in_a_question(): void
    {
        $this->fakeGroq([
            $this->text('¡Listo! Tu cita quedó agendada para el martes a las 10:00 AM. ¿Necesitas algo más?'),
            $this->text('Perdona, aún no la tengo agendada. ¿Te la reservo?'),
        ]);

        $reply = $this->coordinator()->reply($this->message('si dale'), $this->provider());

        $this->assertSame('Perdona, aún no la tengo agendada. ¿Te la reservo?', $reply);
    }

    /**
     * Cancelling had no guard at all: the model could tell a client her
     * appointment was gone while it sat in the table blocking a real slot.
     */
    public function test_a_cancellation_the_model_never_performed_is_refused(): void
    {
        $provider = $this->provider();

        Appointment::factory()->for($provider)->create([
            'client_phone' => '2056455856',
            'starts_at' => CarbonImmutable::now()->addDays(2)->setTime(10, 0),
            'ends_at' => CarbonImmutable::now()->addDays(2)->setTime(10, 40),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->fakeGroq([
            $this->text('Listo, tu cita quedó cancelada.'),
            $this->text('Déjame revisarlo un momento.'),
        ]);

        $reply = $this->coordinator()->reply($this->message('cancelame la cita'), $provider);

        $this->assertSame('Déjame revisarlo un momento.', $reply);
        $this->assertDatabaseCount('appointments', 1);
    }

    /**
     * The mirror of the fabricated confirmation, seen in the same benchmark:
     * crear_cita succeeded, the row was in Postgres, and the model answered
     * with a question — the appointment existed and the client had no way to
     * know. The facts appended come from the row, not from its prose.
     */
    public function test_it_adds_the_real_details_when_the_model_books_but_never_says_when(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['name' => 'Corte', 'price' => 45, 'duration_minutes' => 45]);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));

        $this->fakeGroq([
            $this->toolCall('crear_cita', json_encode([
                'servicio_id' => $service->id,
                'fecha' => '2026-08-11',
                'hora' => '10:00',
                'nombre_completo' => 'Jose Sosa',
            ])),
            $this->text('¿Necesitas algo más, bella?'),
        ]);

        $reply = $this->coordinator()->reply($this->message('si correcto, jose sosa'), $provider);

        $this->assertStringContainsString('¿Necesitas algo más, bella?', $reply);
        $this->assertStringContainsString('Corte el martes 11 de agosto a las 10:00 AM', $reply);
        $this->assertStringContainsString('Patricia moreno', $reply);
        $this->assertDatabaseCount('appointments', 1);

        $this->travelBack();
    }

    /**
     * A model that will not stop asserting a booking it never made must not
     * wear the loop down until its last attempt is relayed. It hands off.
     */
    public function test_it_hands_off_rather_than_relaying_a_claim_it_could_not_correct(): void
    {
        $this->fakeGroq([
            $this->text('¡Listo! Tu cita quedó agendada para el martes.'),
            $this->text('Tu cita quedó agendada, bella.'),
            $this->text('La cita está agendada para el martes.'),
        ]);

        $this->expectException(AssistantUnavailable::class);

        $this->coordinator()->reply($this->message('agendame'), $this->provider());
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
