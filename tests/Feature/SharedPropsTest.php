<?php

namespace Tests\Feature;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_have_a_null_auth_user(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_authenticated_users_expose_only_the_whitelisted_fields(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $provider->user->id)
            ->where('auth.user.username', $provider->user->username)
            ->where('auth.user.provider.slug', $provider->slug)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.email_verified_at')
        );
    }
}
