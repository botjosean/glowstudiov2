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
        $this->assertStringContainsString('/p/pati', $prompt);
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
}
