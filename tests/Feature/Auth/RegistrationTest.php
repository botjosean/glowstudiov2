<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'newbarber',
            'fullName' => 'New Barber',
            'phone' => '3055551234',
            'email' => 'newbarber@example.com',
            'password' => 'SuperSecret123',
            'confirmPassword' => 'SuperSecret123',
        ], $overrides);
    }

    public function test_registering_creates_a_user_and_an_unpublished_provider(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'newbarber@example.com')->firstOrFail();
        $this->assertSame('newbarber', $user->username);
        $this->assertSame('New Barber', $user->name);
        $this->assertSame('3055551234', $user->phone);

        $this->assertNotNull($user->provider);
        $this->assertSame('newbarber', $user->provider->slug);
        $this->assertNull($user->provider->published_at);
    }

    public function test_registering_logs_the_user_in(): void
    {
        $this->post('/register', $this->validPayload());

        $this->assertAuthenticated();
    }

    public function test_duplicate_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'newbarber']);

        $response = $this->post('/register', $this->validPayload(['email' => 'other@example.com']));

        $response->assertSessionHasErrors('username');
        $this->assertSame(1, User::count());
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'newbarber@example.com']);

        $response = $this->post('/register', $this->validPayload(['username' => 'otheruser']));

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_duplicate_email_with_different_case_is_rejected_not_a_500(): void
    {
        User::factory()->create(['email' => 'newbarber@example.com']);

        $response = $this->post('/register', $this->validPayload([
            'username' => 'otheruser',
            'email' => 'NewBarber@Example.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        User::factory()->create(['phone' => '3055551234']);

        $response = $this->post('/register', $this->validPayload([
            'username' => 'otheruser',
            'email' => 'other@example.com',
        ]));

        $response->assertSessionHasErrors('phone');
        $this->assertSame(1, User::count());
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->post('/register', $this->validPayload(['confirmPassword' => 'SomethingElse456']));

        $response->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
    }

    public function test_missing_phone_is_rejected(): void
    {
        $response = $this->post('/register', $this->validPayload(['phone' => '']));

        $response->assertSessionHasErrors('phone');
        $this->assertSame(0, User::count());
    }

    /**
     * SignUp.vue formats the phone as the user types ("(305) 555-1234"),
     * so that's what actually hits the wire — not a bare 10-digit string.
     */
    public function test_formatted_phone_is_normalized_before_saving(): void
    {
        $response = $this->post('/register', $this->validPayload(['phone' => '(305) 555-1234']));

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'newbarber@example.com')->firstOrFail();
        $this->assertSame('3055551234', $user->phone);
    }
}
