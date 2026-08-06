<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperadminTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        config(['app.superadmins' => ['admin']]);

        return User::factory()->create(['username' => 'admin']);
    }

    public function test_the_panel_does_not_exist_for_regular_users(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->get('/admin-general')->assertNotFound();
    }

    public function test_the_panel_does_not_exist_for_guests(): void
    {
        // The auth middleware runs first, so guests are sent to log in
        // rather than learning whether the route exists.
        $this->get('/admin-general')->assertRedirect('/iniciar-sesion');
    }

    public function test_a_superadmin_sees_accounts_and_appointments(): void
    {
        $admin = $this->superadmin();
        $provider = Provider::factory()->published()->create([
            'whatsapp_phone_number_id' => '868324373028256',
        ]);
        Appointment::factory()->for($provider)->create(['client_name' => 'Maria Test']);

        $this->actingAs($admin)->get('/admin-general')->assertInertia(fn (Assert $page) => $page
            ->component('Superadmin/Panel')
            ->has('accounts', 2)
            ->has('appointments', 1)
            ->where('appointments.0.clientName', 'Maria Test')
        );
    }

    public function test_wipe_deletes_unprotected_accounts_but_keeps_connected_professionals(): void
    {
        $admin = $this->superadmin();

        $connected = Provider::factory()->published()->create([
            'whatsapp_phone_number_id' => '868324373028256',
        ]);
        Service::factory()->for($connected)->create();
        $realAppointment = Appointment::factory()->for($connected)->create();

        $testProvider = Provider::factory()->create();
        Service::factory()->for($testProvider)->create();
        Appointment::factory()->for($testProvider)->create();

        $providerlessUser = User::factory()->create();

        // Captured before the wipe: afterwards the relation resolves to null.
        $testUserId = $testProvider->user_id;

        $this->actingAs($admin)->post('/admin-general/limpiar')->assertRedirect();

        $this->assertNull(User::find($testUserId));
        $this->assertNull(Provider::find($testProvider->id));
        $this->assertNull(User::find($providerlessUser->id));

        $this->assertNotNull(User::find($admin->id));
        $this->assertNotNull(Provider::find($connected->id));
        $this->assertNotNull(User::find($connected->user->id));
        $this->assertNotNull(Appointment::find($realAppointment->id));
        $this->assertSame(1, $connected->services()->count());
    }

    public function test_a_single_appointment_can_be_deleted(): void
    {
        $admin = $this->superadmin();
        $provider = Provider::factory()->published()->create([
            'whatsapp_phone_number_id' => '868324373028256',
        ]);
        $appointment = Appointment::factory()->for($provider)->create();

        $this->actingAs($admin)->delete("/admin-general/citas/{$appointment->id}")->assertRedirect();

        $this->assertNull(Appointment::find($appointment->id));
    }

    public function test_wipe_is_not_available_to_regular_users(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin-general/limpiar')->assertNotFound();
    }

    public function test_a_providerless_superadmin_is_redirected_from_the_provider_panel(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin/inicio')->assertRedirect('/admin-general');
    }
}
