<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_pins_a_server_generated_webp_to_the_card(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create();

        $response = $this->actingAs($provider->user)->post("/admin/clientes/{$client->id}/fotos", [
            'photo' => UploadedFile::fake()->image('after.jpg', 800, 800),
        ]);

        $response->assertSessionHasNoErrors();

        $photo = $client->photos()->sole();
        $this->assertStringStartsWith("providers/{$provider->id}/gallery/", $photo->url);
        $this->assertStringEndsWith('.webp', $photo->url);
        Storage::disk('r2')->assertExists($photo->url);
    }

    public function test_the_photo_limit_is_enforced(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create();

        for ($i = 0; $i < Client::MAX_PHOTOS; $i++) {
            $client->photos()->create(['url' => "providers/{$provider->id}/gallery/photo-{$i}.webp"]);
        }

        $this->actingAs($provider->user)->post("/admin/clientes/{$client->id}/fotos", [
            'photo' => UploadedFile::fake()->image('after.jpg'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_deleting_is_scoped_to_the_owner_and_the_card(): void
    {
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create();
        $photo = $client->photos()->create(['url' => 'providers/1/gallery/x.webp']);

        // Another provider cannot touch it.
        $foreignProvider = Provider::factory()->published()->create();
        $this->actingAs($foreignProvider->user)
            ->delete("/admin/clientes/{$client->id}/fotos/{$photo->id}")
            ->assertForbidden();

        // A photo id from a different card 404s instead of leaking.
        $otherClient = Client::factory()->for($provider)->create();
        $this->actingAs($provider->user)
            ->delete("/admin/clientes/{$otherClient->id}/fotos/{$photo->id}")
            ->assertNotFound();

        $this->actingAs($provider->user)
            ->delete("/admin/clientes/{$client->id}/fotos/{$photo->id}")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('client_photos', ['id' => $photo->id]);
    }

    public function test_uploads_from_foreign_providers_are_forbidden(): void
    {
        $client = Client::factory()->create();
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post("/admin/clientes/{$client->id}/fotos", [
            'photo' => UploadedFile::fake()->image('after.jpg'),
        ])->assertForbidden();
    }
}
