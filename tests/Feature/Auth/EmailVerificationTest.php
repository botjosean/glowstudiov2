<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'username' => 'newbarber',
            'fullName' => 'New Barber',
            'phone' => '3055551234',
            'email' => 'newbarber@example.com',
            'password' => 'SuperSecret123',
            'confirmPassword' => 'SuperSecret123',
        ]);

        $user = User::where('email', 'newbarber@example.com')->firstOrFail();

        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_unverified_user_is_redirected_to_the_notice_page(): void
    {
        $provider = Provider::factory()->for(User::factory()->unverified(), 'user')->published()->create();

        $this->actingAs($provider->user)->get('/admin/citas')->assertRedirect('/verificar-correo');
    }

    public function test_verified_user_is_not_redirected(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->get('/admin/citas')->assertOk();
    }

    public function test_the_notice_page_shows_the_users_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);
        Provider::factory()->for($user, 'user')->published()->create();

        $this->actingAs($user)->get('/verificar-correo')->assertInertia(
            fn ($page) => $page->component('Auth/VerifyEmail')->where('email', 'pending@example.com')
        );
    }

    public function test_clicking_the_signed_link_verifies_the_email_and_grants_access(): void
    {
        $user = User::factory()->unverified()->create();
        Provider::factory()->for($user, 'user')->published()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/admin/citas?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get('/admin/citas')->assertOk();
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        Provider::factory()->for($user, 'user')->published()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('not-the-real-email'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resending_the_verification_email_flashes_a_success_message_and_resends(): void
    {
        Notification::fake();

        $provider = Provider::factory()->for(User::factory()->unverified(), 'user')->published()->create();

        $response = $this->actingAs($provider->user)->post('/email/verification-notification');

        $response->assertRedirect();
        $this->assertSame('verifyEmail.linkSent', session('success'));
        Notification::assertSentTo($provider->user, QueuedVerifyEmail::class);
    }
}
