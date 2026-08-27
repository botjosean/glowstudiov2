<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessCategory;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'newusername',
            'publicName' => 'New Public Name',
            'phone' => '(305) 555-0142',
            'bio' => 'Updated bio.',
            // Required by UpdateProfileRequest since the location fields
            // landed; without it these three tests fail on validation before
            // reaching what they actually assert. Left red for a while waiting
            // for the original author, which only made the suite's real
            // failures harder to see.
            'isMobile' => false,
        ], $overrides);
    }

    public function test_updating_the_profile(): void
    {
        $user = User::factory()->create(['username' => 'oldusername']);
        $provider = Provider::factory()->for($user)->published()->create();

        $response = $this->actingAs($user)->put('/admin/perfil', $this->validPayload());

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $provider->refresh();
        $this->assertSame('newusername', $user->username);
        $this->assertSame('3055550142', $user->phone);
        $this->assertSame('New Public Name', $provider->public_name);
        $this->assertSame('Updated bio.', $provider->bio);
        $this->assertSame('admin.profileUpdated', session('success'));
    }

    public function test_business_category_is_saved(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['businessCategory' => 'barbershop']))
            ->assertSessionHasNoErrors();

        $this->assertSame(BusinessCategory::Barbershop, $user->fresh()->provider->business_category);
    }

    public function test_business_category_is_optional(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->provider->business_category);
    }

    public function test_an_unknown_business_category_is_rejected(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['businessCategory' => 'astrology']))
            ->assertSessionHasErrors('businessCategory');
    }

    public function test_username_is_lowercased_on_save(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)->put('/admin/perfil', $this->validPayload(['username' => 'PatiB']));

        $this->assertSame('patib', $user->fresh()->username);
    }

    public function test_username_taken_by_another_user_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
    }

    public function test_username_taken_in_a_different_case_is_rejected(): void
    {
        // Regression test: Rule::unique compares the submitted value, and
        // without normalizing case first, "TAKEN" would pass unique against
        // a stored "taken", then the model's mutator lowercases on save,
        // producing a 500 unique-index violation instead of a 422.
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['username' => 'TAKEN']))
            ->assertSessionHasErrors('username');
    }

    public function test_username_with_invalid_characters_is_rejected(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['username' => 'has spaces!']))
            ->assertSessionHasErrors('username');
    }

    public function test_username_too_short_is_rejected(): void
    {
        $user = User::factory()->create();
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['username' => 'ab']))
            ->assertSessionHasErrors('username');
    }

    public function test_resubmitting_your_own_username_succeeds(): void
    {
        // Proves Rule::unique(...)->ignore($this->user()->id) is actually wired.
        $user = User::factory()->create(['username' => 'mine']);
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)
            ->put('/admin/perfil', $this->validPayload(['username' => 'mine']))
            ->assertSessionHasNoErrors();
    }

    public function test_email_cannot_be_changed_through_this_endpoint(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        Provider::factory()->for($user)->published()->create();

        $this->actingAs($user)->put('/admin/perfil', array_merge(
            $this->validPayload(),
            ['email' => 'hijacked@example.com'],
        ));

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_publishing_with_an_active_service_succeeds(): void
    {
        $provider = Provider::factory()->unpublished()->create();
        Service::factory()->for($provider)->create(['is_active' => true]);

        $this->actingAs($provider->user)
            ->patch('/admin/perfil/publicacion', ['published' => true])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($provider->fresh()->published_at);
    }

    public function test_publishing_without_an_active_service_is_blocked(): void
    {
        $provider = Provider::factory()->unpublished()->create();
        Service::factory()->for($provider)->inactive()->create();

        $this->actingAs($provider->user)
            ->patch('/admin/perfil/publicacion', ['published' => true])
            ->assertSessionHasErrors('published');

        $this->assertNull($provider->fresh()->published_at);
    }

    public function test_unpublishing_always_succeeds(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->patch('/admin/perfil/publicacion', ['published' => false])
            ->assertSessionHasNoErrors();

        $this->assertNull($provider->fresh()->published_at);
    }

    public function test_a_provider_cannot_change_another_providers_profile(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create(['public_name' => 'Theirs']);

        $this->actingAs($mine->user)->put('/admin/perfil', $this->validPayload());

        $theirs->refresh();
        $this->assertSame('Theirs', $theirs->public_name);
        $this->assertNotNull($theirs->published_at);
    }
}
