<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_a_password_can_set_one_and_lands_on_the_profile_page(): void
    {
        $user = User::factory()->create(['password' => null]);
        Provider::factory()->for($user, 'user')->create();

        $response = $this->actingAs($user)->put('/crear-contrasena', [
            'password' => 'SuperSecret123',
            'confirmPassword' => 'SuperSecret123',
        ]);

        $response->assertRedirect('/admin/perfil');
        $this->assertTrue(Hash::check('SuperSecret123', $user->fresh()->password));
    }

    public function test_a_confirmation_mismatch_is_rejected_and_leaves_the_password_unset(): void
    {
        $user = User::factory()->create(['password' => null]);
        Provider::factory()->for($user, 'user')->create();

        $response = $this->actingAs($user)->put('/crear-contrasena', [
            'password' => 'SuperSecret123',
            'confirmPassword' => 'SomethingElse456',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->password);
    }

    /**
     * Without this, anyone already logged in with a real password could hit
     * this endpoint directly and overwrite it — the one thing this step is
     * not allowed to do, since it asks for no current_password.
     */
    public function test_a_user_who_already_has_a_password_cannot_use_this_endpoint(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user, 'user')->create();
        $originalHash = $user->password;

        $response = $this->actingAs($user)->put('/crear-contrasena', [
            'password' => 'TakeoverAttempt123',
            'confirmPassword' => 'TakeoverAttempt123',
        ]);

        $response->assertForbidden();
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_a_user_without_a_password_is_redirected_from_the_admin_panel(): void
    {
        $user = User::factory()->create(['password' => null]);
        Provider::factory()->for($user, 'user')->published()->create();

        $this->actingAs($user)->get('/admin/citas')->assertRedirect('/crear-contrasena');
    }

    public function test_a_user_with_a_password_is_not_redirected(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->get('/admin/citas')->assertOk();
    }
}
