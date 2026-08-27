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
            ->create(['public_name' => 'Pati Barber', 'bio' => 'Great cuts.', 'business_category' => 'barbershop']);

        $this->actingAs($provider->user)->get('/admin/negocio')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Negocio')
            ->where('profile.username', 'patib')
            ->where('profile.publicName', 'Pati Barber')
            ->where('profile.phone', '(305) 555-0142')
            ->where('profile.email', 'pati@example.com')
            ->where('profile.businessCategory', 'barbershop')
            ->where('profile.bio', 'Great cuts.')
            ->where('profile.published', true)
            ->has('publicUrl')
        );
    }

    public function test_business_category_is_null_when_not_chosen_yet(): void
    {
        $provider = Provider::factory()->published()->create(['business_category' => null]);

        $this->actingAs($provider->user)->get('/admin/negocio')->assertInertia(fn (Assert $page) => $page
            ->where('profile.businessCategory', null)
        );
    }

    public function test_business_subcategories_are_exposed(): void
    {
        $provider = Provider::factory()->published()->create([
            'business_category' => 'hair',
            'business_subcategories' => ['color', 'balayage_highlights'],
        ]);

        $this->actingAs($provider->user)->get('/admin/negocio')->assertInertia(fn (Assert $page) => $page
            ->where('profile.businessSubcategories', ['color', 'balayage_highlights'])
        );
    }

    public function test_business_subcategories_default_to_an_empty_list(): void
    {
        $provider = Provider::factory()->published()->create(['business_category' => null, 'business_subcategories' => null]);

        $this->actingAs($provider->user)->get('/admin/negocio')->assertInertia(fn (Assert $page) => $page
            ->where('profile.businessSubcategories', [])
        );
    }
}
