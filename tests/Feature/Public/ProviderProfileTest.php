<?php

namespace Tests\Feature\Public;

use App\Enums\ServiceIcon;
use App\Models\Provider;
use App\Models\ProviderPhoto;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_provider_404s(): void
    {
        $provider = Provider::factory()->unpublished()->create(['slug' => 'hidden']);

        $this->get("/p/{$provider->slug}")->assertNotFound();
    }

    public function test_nonexistent_slug_404s(): void
    {
        $this->get('/p/does-not-exist')->assertNotFound();
    }

    public function test_location_title_and_subtitle_are_never_empty_without_area_or_address(): void
    {
        // Profile.vue renders provider.location.title/.subtitle with no
        // v-if — both must always be non-empty, even for a provider with
        // neither a service_area nor an address_line set.
        $provider = Provider::factory()->published()->create([
            'is_mobile' => false,
            'is_available_now' => false,
            'service_area' => null,
            'address_line' => null,
        ]);

        $this->get("/p/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.location.title', fn ($title) => filled($title))
            ->where('provider.location.subtitle', fn ($subtitle) => filled($subtitle))
        );
    }

    public function test_service_without_a_type_falls_back_to_the_scissors_icon(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create(['service_type_id' => null]);

        $this->get("/p/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.services.0.icon', ServiceIcon::Scissors->value)
        );
    }

    public function test_gallery_and_social_links_are_included(): void
    {
        $provider = Provider::factory()->published()->create([
            'whatsapp_url' => 'https://wa.me/13055550100',
            'instagram_url' => null,
        ]);
        ProviderPhoto::factory()->for($provider)->create(['url' => 'https://example.com/1.jpg', 'position' => 0]);

        $this->get("/p/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.gallery.0', 'https://example.com/1.jpg')
            ->where('provider.social.whatsapp', 'https://wa.me/13055550100')
            ->missing('provider.social.instagram')
        );
    }

    public function test_inactive_services_are_not_shown(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create(['is_active' => true]);
        Service::factory()->for($provider)->inactive()->create();

        $this->get("/p/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->has('provider.services', 1)
        );
    }
}
