<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The login rate limiter keys on identifier + IP; flush so tests
        // don't bleed into each other's quota.
        Cache::flush();
    }

    public function test_can_log_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'pati@example.com']);

        $response = $this->post('/login', ['identifier' => 'pati@example.com', 'password' => 'password']);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_can_log_in_with_username(): void
    {
        $user = User::factory()->create(['username' => 'patib']);

        $response = $this->post('/login', ['identifier' => 'patib', 'password' => 'password']);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'patib']);

        $response = $this->post('/login', ['identifier' => 'PATIB', 'password' => 'password']);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_produces_an_error_on_identifier(): void
    {
        User::factory()->create(['username' => 'patib']);

        $response = $this->post('/login', ['identifier' => 'patib', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    /**
     * A regular login never checks the checklist — only VerifyEmailResponse
     * does, right after creating the account. An incomplete profile stays a
     * banner, not a place login keeps dropping her back into.
     */
    public function test_successful_login_redirects_to_the_admin_panel(): void
    {
        Provider::factory()->for(User::factory()->state(['username' => 'patib']))->published()->create();

        $response = $this->post('/login', ['identifier' => 'patib', 'password' => 'password']);

        $response->assertRedirect('/admin/citas');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['username' => 'patib']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => 'patib', 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['identifier' => 'patib', 'password' => 'wrong']);

        // The `throttle:login` route middleware trips first with a raw 429.
        // bootstrap/app.php converts that into the same 'identifier' error
        // SignIn.vue already renders for wrong credentials, so the lockout
        // is actually visible instead of the form silently doing nothing.
        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_the_rate_limit_message_names_the_wait(): void
    {
        User::factory()->create(['username' => 'patib']);

        for ($i = 0; $i < 6; $i++) {
            $response = $this->withUnencryptedCookie('locale', 'es')->post('/login', ['identifier' => 'patib', 'password' => 'wrong']);
        }

        $response->assertSessionHasErrors([
            'identifier' => 'Demasiados intentos. Probá de nuevo en 1 minuto.',
        ]);
    }

    public function test_the_rate_limit_message_respects_the_english_locale(): void
    {
        User::factory()->create(['username' => 'patib']);

        for ($i = 0; $i < 6; $i++) {
            $response = $this->withUnencryptedCookie('locale', 'en')->post('/login', ['identifier' => 'patib', 'password' => 'wrong']);
        }

        $response->assertSessionHasErrors([
            'identifier' => 'Too many attempts. Try again in 1 minute.',
        ]);
    }

    public function test_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }
}
