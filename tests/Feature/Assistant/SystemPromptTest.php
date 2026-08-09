<?php

namespace Tests\Feature\Assistant;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Support\Assistant\SystemPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prompt_carries_the_providers_own_booking_link(): void
    {
        // The assistant offers this link to clients who would rather pick a
        // time themselves. It is built from APP_URL because the prompt is
        // rendered inside a queued job, with no request to infer a host from —
        // a wrong APP_URL would send real clients somewhere dead, so it is
        // worth failing here rather than in a conversation.
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $prompt = app(SystemPrompt::class)->for($provider);

        $this->assertStringContainsString(route('providers.show', $provider), $prompt);
        $this->assertStringContainsString('/pati', $prompt);
    }

    public function test_the_prompt_never_leaks_another_providers_link(): void
    {
        $mine = Provider::factory()->published()->create(['slug' => 'pati']);
        $theirs = Provider::factory()->published()->create(['slug' => 'vane']);

        $prompt = app(SystemPrompt::class)->for($mine);

        $this->assertStringNotContainsString(route('providers.show', $theirs), $prompt);
    }

    public function test_the_prompt_lists_only_the_providers_active_services(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create(['name' => 'Acrilicas', 'price' => 65]);
        Service::factory()->for($provider)->inactive()->create(['name' => 'Servicio retirado']);
        Service::factory()->for(Provider::factory()->published()->create())->create(['name' => 'De otra profesional']);

        $prompt = app(SystemPrompt::class)->for($provider);

        $this->assertStringContainsString('Acrilicas', $prompt);
        $this->assertStringNotContainsString('Servicio retirado', $prompt);
        $this->assertStringNotContainsString('De otra profesional', $prompt);
    }

    public function test_the_prompt_states_the_real_working_days_and_hours(): void
    {
        // The gap this closes: with no schedule in the prompt, "what days are
        // you open?" had no answer. One model went silent, another invented
        // "Tuesday to Saturday, 9 to 6" for a salon that opens at 11 daily.
        $provider = Provider::factory()->withSchedule(11 * 60, 22 * 60, 0, 0, 30)->published()->create();
        $provider->businessHours()->where('weekday', 0)->update(['is_open' => false]);

        $prompt = app(SystemPrompt::class)->for($provider->fresh());

        $this->assertStringContainsString('HORARIO', $prompt);
        // 12-hour with the suffix spelled out: the assistant repeats this
        // verbatim to clients, so military time here becomes military time
        // on WhatsApp — which is exactly the complaint that changed it.
        $this->assertStringContainsString('11:00 AM', $prompt);
        $this->assertStringContainsString('10:00 PM', $prompt);
        $this->assertStringNotContainsString('22:00', $prompt);
        $this->assertStringContainsString('No se atiende: domingo', $prompt);
    }

    public function test_identical_hours_every_day_collapse_to_one_line(): void
    {
        // Spelling out seven identical days made the assistant repeat a wall of
        // text to clients, against its own "short WhatsApp messages" rule.
        $provider = Provider::factory()->withSchedule(10 * 60, 20 * 60, 0, 0, 15)->published()->create();

        $prompt = app(SystemPrompt::class)->for($provider);

        $this->assertStringContainsString('Se atiende todos los días de 10:00 AM a 8:00 PM', $prompt);
        $this->assertStringNotContainsString('lunes de 10:00', $prompt);
    }

    public function test_the_prompt_never_mentions_lunch(): void
    {
        // The owner does not want the assistant narrating when the
        // professional eats. The availability tool already refuses lunch
        // slots, so a time inside lunch is simply "not available" — the
        // prompt must not give the assistant the words to explain why.
        // Distinctive :15 offsets so the assertion cannot collide with the
        // work window or with anything in negocio.md.
        $provider = Provider::factory()
            ->withSchedule(10 * 60, 20 * 60, 13 * 60 + 15, 14 * 60 + 15, 15)
            ->published()
            ->create();

        $prompt = app(SystemPrompt::class)->for($provider);

        $this->assertStringNotContainsString('Pausa', $prompt);
        $this->assertStringNotContainsString('1:15 PM', $prompt);
        $this->assertStringNotContainsString('2:15 PM', $prompt);
    }

    public function test_earlier_than_opening_asks_for_human_coordination(): void
    {
        // Early appointments can happen, but only agreed directly with the
        // professional — the assistant offers the possibility and escalates,
        // never books outside the schedule itself.
        $provider = Provider::factory()->published()->create(['public_name' => 'Patricia']);

        $prompt = app(SystemPrompt::class)->for($provider);

        $this->assertStringContainsString('más temprana que la apertura', $prompt);
        $this->assertStringContainsString('coordinándolo directamente con Patricia', $prompt);
        $this->assertStringContainsString('Nunca crees tú una cita fuera del horario', $prompt);
    }

    public function test_blocked_dates_reach_the_prompt(): void
    {
        $provider = Provider::factory()->published()->create();
        $provider->timeOff()->create([
            'starts_on' => now()->addDays(3)->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
            'reason' => 'Vacaciones',
        ]);

        $this->assertStringContainsString('Cerrado además estos días', app(SystemPrompt::class)->for($provider));
    }

    public function test_a_fully_closed_week_tells_the_assistant_to_offer_nothing(): void
    {
        $provider = Provider::factory()->published()->create();
        $provider->businessHours()->update(['is_open' => false]);

        $this->assertStringContainsString('no se atiende ningún día', app(SystemPrompt::class)->for($provider->fresh()));
    }

    public function test_a_client_with_appointments_is_recognized_by_phone(): void
    {
        // Professionals book walk-ins by hand from the agenda; when that
        // client writes on WhatsApp, the assistant must greet her by name
        // and know her appointment instead of interrogating her again.
        $provider = Provider::factory()->published()->create();
        Appointment::factory()->for($provider)->confirmed()->create([
            'client_name' => 'Sandra Ríos',
            'client_phone' => '4045550123',
            'service_name' => 'Balayage',
        ]);

        $prompt = app(SystemPrompt::class)->for($provider, '14045550123');

        $this->assertStringContainsString('CLIENTA CONOCIDA', $prompt);
        $this->assertStringContainsString('Sandra Ríos', $prompt);
        $this->assertStringContainsString('Balayage', $prompt);
        $this->assertStringContainsString('confirmada', $prompt);
    }

    public function test_an_unknown_phone_adds_no_client_block(): void
    {
        // "Quien te escribe es" is the block's own opening — the rules section
        // legitimately names "sección CLIENTA CONOCIDA", so asserting on that
        // marker would always fail.
        $provider = Provider::factory()->published()->create();

        $this->assertStringNotContainsString('Quien te escribe es', app(SystemPrompt::class)->for($provider, '14045550199'));
        $this->assertStringNotContainsString('Quien te escribe es', app(SystemPrompt::class)->for($provider));
    }

    public function test_recognition_never_leaks_across_providers(): void
    {
        // The same phone may be a client of two professionals; each prompt
        // must only carry the appointments of the provider being written to.
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        Appointment::factory()->for($theirs)->confirmed()->create([
            'client_name' => 'Clienta Ajena',
            'client_phone' => '4045550123',
        ]);

        $this->assertStringNotContainsString('Clienta Ajena', app(SystemPrompt::class)->for($mine, '14045550123'));
    }

    public function test_home_capable_services_are_marked_only_in_both_mode(): void
    {
        // "Both" mode: marked services carry the home note and the location
        // section teaches the escalation rule. Studio-only providers must
        // never see the words — nothing for the model to negotiate with.
        $both = Provider::factory()->published()->create([
            'home_service' => true, 'is_mobile' => false, 'address_line' => 'Calle 1 #2-3', 'service_area' => 'Brookhaven',
        ]);
        Service::factory()->for($both)->create(['name' => 'Manicure Deluxe', 'home_available' => true]);
        Service::factory()->for($both)->create(['name' => 'Corte Clasico', 'home_available' => false]);

        $prompt = app(SystemPrompt::class)->for($both);

        $this->assertMatchesRegularExpression('/Manicure Deluxe.*se puede a domicilio, previa coordinación/', $prompt);
        $this->assertDoesNotMatchRegularExpression('/Corte Clasico.*se puede a domicilio/', $prompt);
        $this->assertStringContainsString('ÚNICAMENTE previa coordinación', $prompt);
        $this->assertStringContainsString('en la zona de Brookhaven', $prompt);

        $studio = Provider::factory()->published()->create([
            'home_service' => false, 'is_mobile' => false, 'address_line' => 'Calle 9 #9-9',
        ]);
        Service::factory()->for($studio)->create(['name' => 'Manicure Deluxe', 'home_available' => true]);

        $studioPrompt = app(SystemPrompt::class)->for($studio);

        $this->assertStringNotContainsString('se puede a domicilio', $studioPrompt);
        $this->assertStringNotContainsString('ÚNICAMENTE previa coordinación', $studioPrompt);
    }
}
