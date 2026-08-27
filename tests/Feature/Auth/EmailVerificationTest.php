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

    /**
     * The notice page polls itself while waiting; this redirect is what
     * moves the waiting tab into the panel once the link is clicked.
     */
    public function test_a_verified_user_polling_the_notice_page_is_sent_to_the_panel(): void
    {
        $provider = Provider::factory()->onboarded()->create();

        $this->actingAs($provider->user)->get('/verificar-correo')->assertRedirect('/admin/citas');
    }

    /**
     * Same rule as LoginResponse/VerifyEmailResponse: a tab left open on
     * this notice page shouldn't skip the guided checklist just because
     * verification itself already happened in another tab.
     */
    public function test_a_verified_user_with_unfinished_onboarding_polling_the_notice_page_is_sent_to_inicio(): void
    {
        $provider = Provider::factory()->create();

        $this->actingAs($provider->user)->get('/verificar-correo')->assertRedirect('/admin/inicio');
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
        Provider::factory()->for($user, 'user')->onboarded()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/admin/citas');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get('/admin/citas')->assertOk();
    }

    /**
     * The actual first-run case: a brand-new signup's checklist is empty, so
     * verifying is what lands her on the guided Admin/Inicio.vue instead of
     * an empty agenda — see App\Http\Responses\VerifyEmailResponse.
     */
    public function test_clicking_the_signed_link_with_unfinished_onboarding_lands_on_inicio(): void
    {
        $user = User::factory()->unverified()->create();
        Provider::factory()->for($user, 'user')->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/admin/inicio');
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
