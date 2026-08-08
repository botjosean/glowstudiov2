<?php

namespace Tests\Feature\Public;

use App\Enums\ServiceCategory;
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

        $this->get("/{$provider->slug}")->assertNotFound();
    }

    public function test_nonexistent_slug_404s(): void
    {
        $this->get('/does-not-exist')->assertNotFound();
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

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.location.title', fn ($title) => filled($title))
            ->where('provider.location.subtitle', fn ($subtitle) => filled($subtitle))
        );
    }

    public function test_service_icon_comes_from_its_category_not_its_catalog_type(): void
    {
        // Profile.vue looks the icon name up in a map with no fallback, so a
        // typeless service still has to resolve to a real one — and half the
        // categories (the beauty ones) never get a catalog type at all.
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create([
            'service_type_id' => null,
            'category' => ServiceCategory::Nails,
        ]);

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.services.0.icon', ServiceIcon::Gem->value)
        );
    }

    public function test_a_barbershop_service_keeps_the_icon_it_had_before_the_category_move(): void
    {
        // Pins that routing icons through ServiceCategory reproduced what the
        // matching ServiceTypes already carried — no live service changed
        // appearance when the source of the icon moved.
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create(['category' => ServiceCategory::Fade]);

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
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

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
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

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->has('provider.services', 1)
        );
    }
}
