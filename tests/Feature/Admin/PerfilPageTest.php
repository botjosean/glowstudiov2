<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\ProviderPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerfilPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_exposes_formatted_phone_and_gallery_with_max(): void
    {
        $user = User::factory()->create(['username' => 'patib', 'phone' => '3055550142', 'email' => 'pati@example.com']);
        $provider = Provider::factory()->published()->for($user)
            ->create(['public_name' => 'Pati Barber', 'bio' => 'Great cuts.']);
        ProviderPhoto::factory()->for($provider)->create(['url' => 'https://example.com/1.jpg', 'position' => 0]);
        ProviderPhoto::factory()->for($provider)->create(['url' => 'https://example.com/2.jpg', 'position' => 1]);

        $this->actingAs($provider->user)->get('/admin/perfil')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Perfil')
            ->where('profile.username', 'patib')
            ->where('profile.publicName', 'Pati Barber')
            ->where('profile.phone', '(305) 555-0142')
            ->where('profile.email', 'pati@example.com')
            ->where('profile.bio', 'Great cuts.')
            ->has('profile.gallery', 2)
            ->where('profile.maxGallery', Provider::MAX_GALLERY_PHOTOS)
        );
    }
}
