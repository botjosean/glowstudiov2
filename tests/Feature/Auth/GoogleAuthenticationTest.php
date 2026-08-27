<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_google_account_creates_a_user_and_an_unpublished_provider(): void
    {
        $this->fakeGoogleUser('nueva@example.com', 'Nueva Estilista');

        $response = $this->get('/auth/google/callback');

        $user = User::where('email', 'nueva@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertNotNull($user->provider);
        $this->assertNull($user->provider->published_at);
        $response->assertRedirect('/crear-contrasena');
    }

    public function test_an_existing_verified_account_with_a_password_logs_in_directly(): void
    {
        $user = User::factory()->create(['email' => 'pati@example.com']);
        Provider::factory()->for($user, 'user')->onboarded()->create();

        $this->fakeGoogleUser('pati@example.com', 'Patricia');

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/admin/citas');
    }

    public function test_an_existing_unverified_account_is_verified_by_signing_in_with_google(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'sinverificar@example.com']);
        Provider::factory()->for($user, 'user')->published()->create();

        $this->fakeGoogleUser('sinverificar@example.com', 'Sin Verificar');

        $this->get('/auth/google/callback');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_existing_account_without_a_password_is_sent_back_to_set_one(): void
    {
        $user = User::factory()->create(['email' => 'incompleta@example.com', 'password' => null]);
        Provider::factory()->for($user, 'user')->create();

        $this->fakeGoogleUser('incompleta@example.com', 'Incompleta');

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/crear-contrasena');
    }

    public function test_two_google_signups_with_colliding_usernames_get_distinct_ones(): void
    {
        User::factory()->create(['username' => 'ana']);

        $this->fakeGoogleUser('ana@example.com', 'Ana Otra');

        $this->get('/auth/google/callback');

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertNotSame('ana', $user->username);
        $this->assertMatchesRegularExpression('/^ana-\d+$/', $user->username);
    }

    /**
     * SignUp.vue's manual form validates username against
     * `^[A-Za-z0-9._]+$`; a Google email's local part can contain characters
     * (like `+`) that regex rejects, so the generated username must be
     * sanitised, not passed through.
     */
    public function test_an_email_with_characters_outside_the_username_regex_is_sanitised(): void
    {
        $this->fakeGoogleUser('jose+test@example.com', 'Jose Test');

        $this->get('/auth/google/callback');

        $user = User::where('email', 'jose+test@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._]+$/', $user->username);
    }

    public function test_a_google_failure_flashes_an_error_instead_of_a_server_error(): void
    {
        Socialite::shouldReceive('driver')->with('google')->andReturn(
            tap(\Mockery::mock(SocialiteProvider::class), function ($provider): void {
                $provider->shouldReceive('user')->andThrow(new \Exception('user cancelled consent'));
            })
        );

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/iniciar-sesion');
        $this->assertSame('auth.googleFailed', session('error'));
        $this->assertGuest();
    }

    public function test_a_google_account_with_no_email_is_rejected_without_creating_anything(): void
    {
        $socialiteUser = (new SocialiteUser)->map(['email' => null, 'name' => 'Sin Correo']);

        Socialite::shouldReceive('driver')->with('google')->andReturn(
            tap(\Mockery::mock(SocialiteProvider::class), function ($provider) use ($socialiteUser): void {
                $provider->shouldReceive('user')->andReturn($socialiteUser);
            })
        );

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/iniciar-sesion');
        $this->assertSame('auth.googleNoEmail', session('error'));
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    private function fakeGoogleUser(string $email, string $name): void
    {
        $socialiteUser = (new SocialiteUser)->map(['email' => $email, 'name' => $name]);

        Socialite::shouldReceive('driver')->with('google')->andReturn(
            tap(\Mockery::mock(SocialiteProvider::class), function ($provider) use ($socialiteUser): void {
                $provider->shouldReceive('user')->andReturn($socialiteUser);
            })
        );
    }
}
