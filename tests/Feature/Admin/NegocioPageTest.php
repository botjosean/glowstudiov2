<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NegocioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editing_form_lives_under_configuracion(): void
    {
        $user = User::factory()->create(['username' => 'patib', 'phone' => '3055550142', 'email' => 'pati@example.com']);
        $provider = Provider::factory()->published()->for($user)
            ->create(['public_name' => 'Pati Barber', 'bio' => 'Great cuts.']);

        $this->actingAs($provider->user)->get('/admin/negocio')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Negocio')
            ->where('profile.username', 'patib')
            ->where('profile.publicName', 'Pati Barber')
            ->where('profile.phone', '(305) 555-0142')
            ->where('profile.email', 'pati@example.com')
            ->where('profile.bio', 'Great cuts.')
            ->where('profile.published', true)
            ->has('publicUrl')
        );
    }
}
