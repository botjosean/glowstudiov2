<?php

namespace Tests\Feature\Admin;

use App\Models\ContentPost;
use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
            ->has('layouts', 3)
            ->has('waiting', 0)
            ->has('references', 0)
            ->has('posts', 0)
        );
    }

    public function test_references_are_listed_so_she_can_see_what_she_saved(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'note' => 'Luz natural.',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]);

        $this->actingAs($provider->user)->get('/admin/contenido')->assertInertia(fn (Assert $page) => $page
            ->has('references', 2)
            ->has('references.0.url')
            ->where('references.0.kind', 'image')
        );
    }

    public function test_a_reference_can_be_removed(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $upload = ContentUpload::query()->where('provider_id', $provider->id)->sole();

        $this->actingAs($provider->user)
            ->delete("/admin/contenido/referencias/{$upload->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContentUpload::query()->count());
        Storage::disk('r2')->assertMissing($upload->path);
    }

    public function test_a_provider_cannot_remove_another_providers_reference(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        $this->actingAs($hers->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $upload = ContentUpload::query()->where('provider_id', $hers->id)->sole();

        $this->actingAs($mine->user)
            ->delete("/admin/contenido/referencias/{$upload->id}")
            ->assertForbidden();

        $this->assertSame(1, ContentUpload::query()->count());
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
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->assertNotSame('', (string) $post->caption);
        $this->assertCount(4, $post->source_paths);
        Storage::disk('r2')->assertExists($post->path);

        // Ya no vuelven a ofrecerse para armar otro post.
        $this->assertSame(0, ContentUpload::query()->where('provider_id', $provider->id)->waiting()->count());
    }

    public function test_the_description_label_does_not_leak_into_the_caption_when_the_model_writes_it_with_an_accent(): void
    {
        // Se le pide el formato "DESCRIPCION:" sin tilde, pero el modelo
        // escribe español de verdad y a veces contesta "DESCRIPCIÓN:" — visto
        // en una generación real para Josean donde la etiqueta quedó pegada
        // al principio del texto del post.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => implode("\n", [
                'TITULAR: CITAS ABIERTAS | ESTA SEMANA',
                'DESCRIPCIÓN: Hoy estuve creando magia en el salón.',
                'HASHTAGS: #hair #hairstylist',
            ])]]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->assertSame('Hoy estuve creando magia en el salón.', $post->caption);
    }

    public function test_a_collage_uses_every_photo_even_when_they_dont_form_a_rectangle(): void
    {
        // Cinco no forman un rectángulo, y aun así entran las cinco: la
        // rejilla arma filas de 3 y 2. Antes se descartaba una en silencio.
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 5))->map(fn () => UploadedFile::fake()->image('x.jpg'))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $this->assertCount(5, ContentPost::query()->sole()->source_paths);
        $this->assertSame(0, ContentUpload::query()->where('provider_id', $provider->id)->waiting()->count());
    }

    public function test_a_carousel_spreads_the_photos_across_several_slides(): void
    {
        // Lo que ella pedía: seis fotos no son un collage de seis cuadraditos,
        // son portada + collages + cierre.
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 6))->map(fn () => UploadedFile::fake()->image('x.jpg'))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'carousel', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        // Portada + collage de 4 + collage de 1 + cierre.
        $this->assertCount(4, $post->slides);
        $this->assertSame($post->slides[0], $post->path);

        foreach ($post->slides as $slide) {
            Storage::disk('r2')->assertExists($slide);
        }
    }

    public function test_a_hero_post_is_built_from_a_single_photo(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => [UploadedFile::fake()->image('sola.jpg', 1200, 900)],
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'hero', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->assertCount(1, $post->source_paths);
        Storage::disk('r2')->assertExists($post->path);
    }

    public function test_a_collage_with_only_one_photo_is_rejected(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => [UploadedFile::fake()->image('uno.jpg')],
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids])
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
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $theirIds])
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

    public function test_an_upload_that_failed_on_the_way_up_is_reported_not_crashed(): void
    {
        // Reproduce un 500 real de producción (29-ago): cuando la subida se
        // corta a medio camino, PHP igual entrega el archivo pero sin ruta, y
        // preguntarle el tipo lanza 'The "" file does not exist'. Tiene que
        // salir un mensaje, no una pantalla de error.
        $provider = Provider::factory()->published()->create();

        $roto = new UploadedFile(
            '/tmp/no-existe-'.uniqid().'.mp4',
            'tutorial.mp4',
            'video/mp4',
            UPLOAD_ERR_INI_SIZE,
            true,
        );

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', ['purpose' => 'reference', 'photos' => [$roto]])
            ->assertSessionHasErrors();

        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_uploading_a_reference_without_a_note_gets_a_suggestion(): void
    {
        // «A la gente le da flojera pensar»: en vez de un cuadro vacío, se le
        // propone una nota mirando lo que subió.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'Me gusta el fondo limpio y las letras doradas.']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->from('/admin/contenido')->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $response->assertSessionHas('noteSuggestion');
        $sugerencia = $response->getSession()->get('noteSuggestion');

        $this->assertSame('Me gusta el fondo limpio y las letras doradas.', $sugerencia['text']);
        $this->assertCount(1, $sugerencia['uploadIds']);
    }

    public function test_no_suggestion_is_asked_when_she_already_wrote_a_note(): void
    {
        // Si ella ya escribió, no hay nada que sugerir ni que pagar.
        Http::fake();

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'note' => 'Ya lo sé, me gusta así.',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $response->assertSessionMissing('noteSuggestion');
        Http::assertNothingSent();
    }

    public function test_no_suggestion_is_asked_past_the_first_batch(): void
    {
        // Las tandas siguientes son las mismas fotos partidas para Cloudflare;
        // sugerir en cada una pagaría lo mismo varias veces.
        Http::fake();

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'first' => '0',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $response->assertSessionMissing('noteSuggestion');
        Http::assertNothingSent();
    }

    public function test_the_suggested_note_can_be_edited_and_saved(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->pluck('id')->all();

        $this->actingAs($provider->user)
            ->patch('/admin/contenido/referencias/nota', ['uploadIds' => $ids, 'note' => 'Lo edité yo.'])
            ->assertSessionHasNoErrors();

        // En la primera, igual que al subir — no repetida en las dos.
        $notas = ContentUpload::query()->where('provider_id', $provider->id)->oldest()->pluck('note')->all();
        $this->assertSame(['Lo edité yo.', null], $notas);
    }

    public function test_a_provider_cannot_set_the_note_of_another_providers_upload(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        $this->actingAs($hers->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $suyo = ContentUpload::query()->where('provider_id', $hers->id)->sole();

        $this->actingAs($mine->user)
            ->patch('/admin/contenido/referencias/nota', ['uploadIds' => [$suyo->id], 'note' => 'no debería entrar'])
            ->assertSessionHasNoErrors(); // no revienta...

        // ...pero tampoco toca la ajena: al no encontrarla scopeada a $mine,
        // updateNote() no actualiza nada.
        $this->assertNull($suyo->fresh()->note);
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
            'layout' => 'collage',
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
            'layout' => 'collage',
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
