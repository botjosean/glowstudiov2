<?php

namespace Tests\Feature\Admin;

use App\Models\ContentPost;
use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
    }

    public function test_the_panel_renders(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->get('/admin/contenido')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Contenido')
            ->where('referenceCount', 0)
            ->where('collagePhotos', 4)
            ->has('waiting', 0)
            ->has('posts', 0)
        );
    }

    public function test_photos_uploaded_for_editing_wait_for_a_model(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', [
                'purpose' => 'edit',
                'photos' => [UploadedFile::fake()->image('uno.jpg'), UploadedFile::fake()->image('dos.jpg')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ContentUpload::query()->where('provider_id', $provider->id)->waiting()->count());
        $this->assertSame(0, ContentUpload::query()->where('provider_id', $provider->id)->references()->count());
    }

    public function test_a_reference_keeps_what_she_said_about_it(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', [
                'purpose' => 'reference',
                'note' => 'Me gusta el fondo bien limpio y que se vea el brillo.',
                'photos' => [UploadedFile::fake()->image('ref.jpg')],
            ])
            ->assertSessionHasNoErrors();

        $upload = ContentUpload::query()->where('provider_id', $provider->id)->references()->sole();

        $this->assertSame('Me gusta el fondo bien limpio y que se vea el brillo.', $upload->note);
        // Una referencia nunca espera un modelo: no se convierte en post.
        $this->assertSame(0, ContentUpload::query()->where('provider_id', $provider->id)->waiting()->count());
    }

    public function test_a_note_is_not_kept_on_photos_meant_for_editing(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'note' => 'esto no va a ninguna parte',
            'photos' => [UploadedFile::fake()->image('uno.jpg')],
        ]);

        $this->assertNull(ContentUpload::query()->where('provider_id', $provider->id)->sole()->note);
    }

    public function test_building_a_collage_makes_a_post_and_marks_the_photos_used(): void
    {
        $provider = Provider::factory()->published()->create(['public_name' => 'Vanessa Moreno']);

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 4))
                ->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg", 900, 1200))
                ->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage_4', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->assertNotSame('', (string) $post->caption);
        $this->assertCount(4, $post->source_paths);
        Storage::disk('r2')->assertExists($post->path);

        // Ya no vuelven a ofrecerse para armar otro post.
        $this->assertSame(0, ContentUpload::query()->where('provider_id', $provider->id)->waiting()->count());
    }

    public function test_building_a_collage_with_too_few_photos_is_rejected(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => [UploadedFile::fake()->image('uno.jpg'), UploadedFile::fake()->image('dos.jpg')],
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage_4', 'uploadIds' => $ids])
            ->assertSessionHasErrors('uploadIds');

        $this->assertSame(0, ContentPost::query()->count());
    }

    public function test_another_providers_photos_cannot_be_pulled_into_a_collage(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        // Cuatro fotos suyas, y unos ids que no me pertenecen.
        $this->actingAs($hers->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 4))->map(fn () => UploadedFile::fake()->image('x.jpg'))->all(),
        ]);

        $theirIds = ContentUpload::query()->where('provider_id', $hers->id)->pluck('id')->all();

        $this->actingAs($mine->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage_4', 'uploadIds' => $theirIds])
            ->assertSessionHasErrors('uploadIds');

        $this->assertSame(0, ContentPost::query()->where('provider_id', $mine->id)->count());
    }

    public function test_a_reference_video_is_stored_untouched(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', [
                'purpose' => 'reference',
                'note' => 'Este explica qué tipografías usar.',
                'photos' => [UploadedFile::fake()->create('tutorial.mp4', 2048, 'video/mp4')],
            ])
            ->assertSessionHasNoErrors();

        $upload = ContentUpload::query()->where('provider_id', $provider->id)->sole();

        $this->assertSame('video', $upload->kind->value);
        // Guardado tal cual: si hubiera pasado por el reencodado de imágenes
        // el video quedaría destruido y con extensión .webp.
        $this->assertStringEndsWith('.mp4', $upload->path);
        Storage::disk('r2')->assertExists($upload->path);
    }

    public function test_a_video_cannot_be_sent_to_build_a_post(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', [
                'purpose' => 'edit',
                'photos' => [UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4')],
            ])
            ->assertSessionHasErrors('photos');

        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_only_one_video_at_a_time(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', [
                'purpose' => 'reference',
                'photos' => [
                    UploadedFile::fake()->create('uno.mp4', 2048, 'video/mp4'),
                    UploadedFile::fake()->create('dos.mp4', 2048, 'video/mp4'),
                ],
            ])
            ->assertSessionHasErrors('photos');

        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_a_reference_note_is_kept_once_not_on_every_photo(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'note' => 'Me gusta la luz natural.',
            'photos' => collect(range(1, 3))->map(fn () => UploadedFile::fake()->image('r.jpg'))->all(),
        ]);

        // Repetirla en las tres hacía que el modelo la viera tres veces y
        // ahogara al resto de las referencias.
        $this->assertSame(
            1,
            ContentUpload::query()->where('provider_id', $provider->id)->whereNotNull('note')->count(),
        );
    }

    public function test_rating_a_post_keeps_the_reason(): void
    {
        $provider = Provider::factory()->published()->create();
        $post = ContentPost::create([
            'provider_id' => $provider->id,
            'layout' => 'collage_4',
            'path' => 'providers/1/content/x.jpg',
            'caption' => 'texto',
        ]);

        $this->actingAs($provider->user)
            ->patch("/admin/contenido/{$post->id}/calificar", ['rating' => 'down', 'note' => 'El marco tapa mucho.'])
            ->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame('down', $post->rating);
        $this->assertSame('El marco tapa mucho.', $post->rating_note);
    }

    public function test_a_provider_cannot_rate_another_providers_post(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        $post = ContentPost::create([
            'provider_id' => $hers->id,
            'layout' => 'collage_4',
            'path' => 'providers/2/content/x.jpg',
            'caption' => 'texto',
        ]);

        $this->actingAs($mine->user)
            ->patch("/admin/contenido/{$post->id}/calificar", ['rating' => 'up'])
            ->assertForbidden();

        $this->assertNull($post->fresh()->rating);
    }

    public function test_the_panel_is_closed_to_guests(): void
    {
        $this->get('/admin/contenido')->assertRedirect('/iniciar-sesion');
    }
}
