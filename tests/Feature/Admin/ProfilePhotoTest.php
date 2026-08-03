<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\ProviderPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function singlePhotoEndpoints(): iterable
    {
        yield 'avatar' => ['/admin/perfil/avatar', 'avatar_photo_url', 'avatar'];
        yield 'banner' => ['/admin/perfil/portada', 'banner_photo_url', 'banner'];
    }

    #[DataProvider('singlePhotoEndpoints')]
    public function test_uploading_a_photo_stores_a_server_generated_webp_key(string $url, string $column, string $variant): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post($url, [
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 800),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/admin/perfil');

        $key = $provider->fresh()->{$column};
        $this->assertNotNull($key);
        $this->assertStringStartsWith("providers/{$provider->id}/{$variant}/", $key);
        $this->assertStringEndsWith('.webp', $key);
        Storage::disk('r2')->assertExists($key);
    }

    #[DataProvider('singlePhotoEndpoints')]
    public function test_replacing_a_photo_deletes_the_previous_object(string $url, string $column, string $variant): void
    {
        Storage::fake('r2');
        $oldKey = "providers/999/{$variant}/01ARZ3NDEKTSV4RRFFQ69G5FAV.webp";
        Storage::disk('r2')->put($oldKey, 'dummy-bytes');

        $provider = Provider::factory()->published()->create([$column => $oldKey]);

        $this->actingAs($provider->user)->post($url, [
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 800),
        ])->assertSessionHasNoErrors();

        $newKey = $provider->fresh()->{$column};
        $this->assertNotSame($oldKey, $newKey);
        Storage::disk('r2')->assertExists($newKey);
        Storage::disk('r2')->assertMissing($oldKey);
    }

    /**
     * @return iterable<string, array{0: int, 1: string, 2: string}>
     */
    public static function invalidPhotos(): iterable
    {
        yield 'too large' => [12 * 1024, 'image/jpeg', 'big.jpg'];
        yield 'pdf renamed to jpg' => [100, 'application/pdf', 'fake.jpg'];
        yield 'svg' => [10, 'image/svg+xml', 'evil.svg'];
    }

    #[DataProvider('invalidPhotos')]
    public function test_an_invalid_upload_is_rejected(int $kilobytes, string $mime, string $name): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/perfil/avatar', [
            'photo' => UploadedFile::fake()->create($name, $kilobytes, $mime),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertNull($provider->fresh()->avatar_photo_url);
    }

    public function test_an_oversized_image_dimension_is_rejected(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/perfil/avatar', [
            'photo' => UploadedFile::fake()->image('wide.jpg', 8000, 100),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertNull($provider->fresh()->avatar_photo_url);
    }

    public function test_uploading_a_gallery_photo_creates_a_row_at_the_next_position(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();
        ProviderPhoto::factory()->for($provider)->create(['position' => 0]);
        ProviderPhoto::factory()->for($provider)->create(['position' => 1]);

        $this->actingAs($provider->user)->post('/admin/perfil/galeria', [
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 800),
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $provider->photos()->count());
        // Provider::photos() bakes in orderBy('position'), which would
        // dominate a chained latest('id') tiebreaker — query the model
        // directly instead to actually get the just-created row.
        $newest = ProviderPhoto::where('provider_id', $provider->id)->latest('id')->first();
        $this->assertSame(2, $newest->position);
        $this->assertStringStartsWith("providers/{$provider->id}/gallery/", $newest->url);
        Storage::disk('r2')->assertExists($newest->url);
    }

    public function test_the_seventh_gallery_photo_is_rejected(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();
        ProviderPhoto::factory()->for($provider)->count(Provider::MAX_GALLERY_PHOTOS)->create();

        $response = $this->actingAs($provider->user)->post('/admin/perfil/galeria', [
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 800),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertSame(Provider::MAX_GALLERY_PHOTOS, $provider->photos()->count());
    }

    public function test_deleting_a_gallery_photo_removes_it_from_r2_and_the_database(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();
        $key = "providers/{$provider->id}/gallery/01ARZ3NDEKTSV4RRFFQ69G5FAV.webp";
        Storage::disk('r2')->put($key, 'dummy-bytes');
        $photo = ProviderPhoto::factory()->for($provider)->create(['url' => $key]);

        $this->actingAs($provider->user)->delete("/admin/perfil/galeria/{$photo->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/perfil');

        $this->assertDatabaseMissing('provider_photos', ['id' => $photo->id]);
        Storage::disk('r2')->assertMissing($key);
    }

    /**
     * A seeded/legacy photo (an absolute URL, not an R2 key) must never be
     * sent to R2's delete endpoint — only the database row is removed.
     */
    public function test_deleting_a_seeded_photo_only_removes_the_database_row(): void
    {
        Storage::fake('r2');
        $provider = Provider::factory()->published()->create();
        $photo = ProviderPhoto::factory()->for($provider)->create(['url' => 'https://images.unsplash.com/photo-1.jpg']);

        $this->actingAs($provider->user)->delete("/admin/perfil/galeria/{$photo->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('provider_photos', ['id' => $photo->id]);
    }

    public function test_a_provider_cannot_delete_another_providers_gallery_photo(): void
    {
        Storage::fake('r2');
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $photo = ProviderPhoto::factory()->for($theirs)->create();

        $this->actingAs($mine->user)->delete("/admin/perfil/galeria/{$photo->id}")->assertForbidden();

        $this->assertDatabaseHas('provider_photos', ['id' => $photo->id]);
    }
}
