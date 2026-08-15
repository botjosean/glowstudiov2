<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\ProviderPhoto;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerfilPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_tab_is_a_showcase_with_progress_stats_and_portfolio(): void
    {
        $provider = Provider::factory()->published()->create(['public_name' => 'Pati Barber', 'bio' => 'Great cuts.']);
        ProviderPhoto::factory()->for($provider)->create(['url' => 'https://example.com/1.jpg', 'position' => 0]);

        Appointment::factory()->for($provider)->create([
            'status' => AppointmentStatus::Confirmed->value,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
        ]);
        Sale::factory()->for($provider)->create(['amount' => 40, 'tip' => 6]);

        $this->actingAs($provider->user)->get('/admin/perfil')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Perfil')
            ->where('publicName', 'Pati Barber')
            ->where('published', true)
            ->has('publicUrl')
            ->has('progress.done')
            ->has('progress.total')
            ->where('stats.upcoming', 1)
            ->where('stats.salesMonth', 46)
            ->has('gallery', 1)
            ->where('maxGallery', Provider::MAX_GALLERY_PHOTOS)
            // The words moved to Configuración → Información del negocio.
            ->missing('profile')
        );
    }
}
