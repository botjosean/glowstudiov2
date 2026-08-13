<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_page_renders(): void
    {
        $this->get('/olvide-contrasena')->assertInertia(
            fn ($page) => $page->component('Auth/ForgotPassword')
        );
    }

    public function test_requesting_a_reset_link_for_a_real_email_sends_it_and_flashes_success(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'pati@example.com']);

        $response = $this->post('/forgot-password', ['email' => 'pati@example.com']);

        $response->assertRedirect();
        $this->assertSame('forgotPassword.linkSent', session('success'));
        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    /**
     * Same flash as the happy path — Fortify's default reveals "we don't
     * know that email" on the email field, which is an account-enumeration
     * leak. See PasswordResetLinkSentResponse.
     */
    public function test_requesting_a_reset_link_for_an_unknown_email_gives_the_same_response(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect();
        $this->assertSame('forgotPassword.linkSent', session('success'));
        Notification::assertNothingSent();
    }

    public function test_the_reset_password_page_renders_with_the_token_and_email(): void
    {
        $this->get('/restablecer-contrasena/some-token?email=pati@example.com')->assertInertia(
            fn ($page) => $page->component('Auth/ResetPassword')
                ->where('token', 'some-token')
                ->where('email', 'pati@example.com')
        );
    }

    public function test_a_valid_token_resets_the_password_and_redirects_to_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'pati@example.com', 'password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'pati@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ]);

        $response->assertRedirect('/iniciar-sesion');
        $this->assertSame('resetPassword.success', session('success'));
        $this->assertTrue(Hash::check('NuevaClave123', $user->fresh()->password));
    }

    public function test_an_invalid_token_is_rejected_and_leaves_the_password_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'pati@example.com', 'password' => Hash::make('old-password')]);

        $response = $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'pati@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ]);

        $response->assertRedirect();
        $this->assertSame('resetPassword.invalidToken', session('error'));
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'pati@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'pati@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'SomethingElse456',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
