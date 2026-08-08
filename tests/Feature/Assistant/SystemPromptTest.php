<?php

namespace Tests\Feature\Assistant;

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
        $this->assertStringContainsString('11:00', $prompt);
        $this->assertStringContainsString('22:00', $prompt);
        $this->assertStringContainsString('No se atiende: domingo', $prompt);
    }

    public function test_identical_hours_every_day_collapse_to_one_line(): void
    {
        // Spelling out seven identical days made the assistant repeat a wall of
        // text to clients, against its own "short WhatsApp messages" rule.
        $provider = Provider::factory()->withSchedule(10 * 60, 20 * 60, 0, 0, 15)->published()->create();

        $prompt = app(SystemPrompt::class)->for($provider);

        $this->assertStringContainsString('Se atiende todos los días de 10:00 a 20:00', $prompt);
        $this->assertStringNotContainsString('lunes de 10:00', $prompt);
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
}
