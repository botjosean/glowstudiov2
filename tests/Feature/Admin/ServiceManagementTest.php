<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Signature Fade',
            'durationMinutes' => 45,
            'price' => 35,
            'category' => 'fade',
        ], $overrides);
    }

    public function test_creating_a_service(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create(['position' => 3]);
        $skinFade = ServiceType::where('slug', 'skin-fade')->firstOrFail();

        $response = $this->actingAs($provider->user)
            ->post('/admin/servicios', $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/admin/servicios');

        $service = Service::where('name', 'Signature Fade')->sole();
        $this->assertSame($provider->id, $service->provider_id);
        $this->assertSame(4, $service->position); // max(3) + 1
        $this->assertSame($skinFade->id, $service->service_type_id); // fade -> skin-fade
        $this->assertTrue($service->is_active);
        $this->assertSame('admin.serviceCreated', session('success'));
    }

    public function test_updating_a_service(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create(['name' => 'Old Name']);

        $response = $this->actingAs($provider->user)
            ->put("/admin/servicios/{$service->id}", $this->validPayload(['name' => 'New Name']));

        $response->assertSessionHasNoErrors();
        $this->assertSame('New Name', $service->fresh()->name);
    }

    public function test_updating_does_not_touch_the_existing_service_type(): void
    {
        // A service whose category doesn't map 1:1 to its seeded type (e.g.
        // "Hot Towel Shave" categorized Beard but typed more specifically)
        // must not have its type clobbered by an unrelated edit.
        $this->seed(ServiceTypeSeeder::class);
        $provider = Provider::factory()->published()->create();
        $specificType = ServiceType::where('slug', 'hot-towel-shave')->firstOrFail();
        $service = Service::factory()->for($provider)->create([
            'category' => 'beard',
            'service_type_id' => $specificType->id,
        ]);

        $this->actingAs($provider->user)
            ->put("/admin/servicios/{$service->id}", $this->validPayload(['category' => 'beard']));

        $this->assertSame($specificType->id, $service->fresh()->service_type_id);
    }

    public function test_deactivating_a_service(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create(['is_active' => true]);

        $response = $this->actingAs($provider->user)
            ->patch("/admin/servicios/{$service->id}/desactivar");

        $response->assertSessionHasNoErrors();
        $service->refresh();
        $this->assertFalse($service->is_active);
        $this->assertNotNull(Service::find($service->id)); // row still exists
    }

    public function test_activating_a_service(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->inactive()->create();

        $this->actingAs($provider->user)->patch("/admin/servicios/{$service->id}/activar");

        $this->assertTrue($service->fresh()->is_active);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'duration not a multiple of 5' => [['durationMinutes' => 47], 'durationMinutes'];
        yield 'duration over max' => [['durationMinutes' => 200], 'durationMinutes'];
        yield 'duration under min' => [['durationMinutes' => 4], 'durationMinutes'];
        yield 'unknown category' => [['category' => 'mohawk'], 'category'];
        yield 'negative price' => [['price' => -1], 'price'];
        yield 'empty name' => [['name' => ''], 'name'];
        yield 'name too long' => [['name' => str_repeat('a', 81)], 'name'];
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_rejects_invalid_payloads(array $overrides, string $field): void
    {
        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)
            ->post('/admin/servicios', $this->validPayload($overrides));

        $response->assertSessionHasErrors($field);
        $this->assertSame(0, Service::count());
    }

    public function test_a_provider_cannot_update_another_providers_service(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirService = Service::factory()->for($theirs)->create(['name' => 'Theirs']);

        $this->actingAs($mine->user)
            ->put("/admin/servicios/{$theirService->id}", $this->validPayload(['name' => 'Hijacked']))
            ->assertForbidden();

        $this->assertSame('Theirs', $theirService->fresh()->name);
    }

    public function test_a_provider_cannot_deactivate_another_providers_service(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirService = Service::factory()->for($theirs)->create(['is_active' => true]);

        $this->actingAs($mine->user)
            ->patch("/admin/servicios/{$theirService->id}/desactivar")
            ->assertForbidden();

        $this->assertTrue($theirService->fresh()->is_active);
    }

    public function test_a_provider_cannot_activate_another_providers_service(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirService = Service::factory()->for($theirs)->inactive()->create();

        $this->actingAs($mine->user)
            ->patch("/admin/servicios/{$theirService->id}/activar")
            ->assertForbidden();

        $this->assertFalse($theirService->fresh()->is_active);
    }

    public function test_cross_tenant_request_is_forbidden_even_with_an_invalid_body(): void
    {
        // Pins the decision that ->can() on the route runs before the
        // FormRequest resolves: a 422 here would leak validation feedback
        // about a resource the caller doesn't own.
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirService = Service::factory()->for($theirs)->create();

        $this->actingAs($mine->user)
            ->put("/admin/servicios/{$theirService->id}", ['name' => '', 'durationMinutes' => -1])
            ->assertForbidden();
    }

    public function test_guests_are_redirected_when_creating_a_service(): void
    {
        $this->post('/admin/servicios', $this->validPayload())->assertRedirect('/iniciar-sesion');
    }

    public function test_a_user_without_a_provider_gets_403_when_creating_a_service(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/servicios', $this->validPayload())->assertForbidden();
    }
}
