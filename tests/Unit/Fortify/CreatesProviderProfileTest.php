<?php

namespace Tests\Unit\Fortify;

use App\Actions\Fortify\CreatesProviderProfile;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatesProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_unpublished_provider_with_a_slug_from_the_seed(): void
    {
        $user = User::factory()->create();

        $provider = (new CreatesProviderProfile)->create($user, 'Ana Perez', 'ana');

        $this->assertSame('ana', $provider->slug);
        $this->assertSame('Ana Perez', $provider->public_name);
        $this->assertSame('America/New_York', $provider->timezone);
        $this->assertNull($provider->published_at);
    }

    public function test_a_colliding_slug_is_disambiguated_with_a_suffix(): void
    {
        Provider::factory()->create(['slug' => 'ana']);
        $user = User::factory()->create();

        $provider = (new CreatesProviderProfile)->create($user, 'Ana Otra', 'ana');

        $this->assertSame('ana-2', $provider->slug);
    }
}
