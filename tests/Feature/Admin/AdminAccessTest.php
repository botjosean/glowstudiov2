<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    public static function adminRoutes(): array
    {
        return ['/admin/citas', '/admin/servicios', '/admin/horario', '/admin/perfil', '/admin/ajustes'];
    }

    public function test_guests_are_redirected_to_sign_in(): void
    {
        foreach (self::adminRoutes() as $route) {
            $this->get($route)->assertRedirect('/iniciar-sesion');
        }
    }

    public function test_an_authenticated_user_without_a_provider_gets_403(): void
    {
        $user = User::factory()->create();

        foreach (self::adminRoutes() as $route) {
            $this->actingAs($user)->get($route)->assertForbidden();
        }
    }

    public function test_a_provider_can_access_their_own_admin_panel(): void
    {
        $provider = Provider::factory()->published()->create();

        foreach (self::adminRoutes() as $route) {
            $this->actingAs($provider->user)->get($route)->assertOk();
        }
    }

    public function test_a_provider_never_sees_another_providers_appointments(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();

        Appointment::factory()->for($theirs)->create(['client_name' => 'Not Mine']);
        Appointment::factory()->for($mine)->create(['client_name' => 'Mine']);

        $this->actingAs($mine->user)->get('/admin/citas')->assertInertia(
            fn ($page) => $page->has('appointments', 1)
                ->where('appointments.0.clientName', 'Mine')
        );
    }

    public function test_a_provider_never_sees_another_providers_services(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();

        Service::factory()->for($theirs)->create(['name' => 'Not Mine']);
        Service::factory()->for($mine)->create(['name' => 'Mine']);

        $this->actingAs($mine->user)->get('/admin/servicios')->assertInertia(
            fn ($page) => $page->has('services', 1)
                ->where('services.0.name', 'Mine')
        );
    }

    public function test_a_provider_never_sees_another_providers_schedule(): void
    {
        $mine = Provider::factory()->withSchedule(600, 1080, 0, 0, 30)->published()->create();
        Provider::factory()->withSchedule(0, 1440, 0, 0, 0)->published()->create();

        $this->actingAs($mine->user)->get('/admin/horario')->assertInertia(
            fn ($page) => $page->where('schedule.workStart', 600)->where('schedule.bufferMinutes', 30)
        );
    }
}
