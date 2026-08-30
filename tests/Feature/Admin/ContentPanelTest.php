<?php

namespace Tests\Feature\Admin;

use App\Actions\Content\HeadlinePhrases;
use App\Actions\Content\WriteCaption;
use App\Enums\BusinessCategory;
use App\Models\ContentPost;
use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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

    public function test_the_colour_of_the_work_is_proposed_so_she_can_correct_it(): void
    {
        // Mirando los píxeles no se puede: las uñas son una parte chica del
        // cuadro y gana la ropa del fondo — comprobado contra fotos reales,
        // donde unas uñas rosa daban "negro". Lo lee el modelo y ella lo
        // corrige, que es lo que ella misma pidió.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"nombre": "rosa degradado a blanco", "hex": "#F2D5D5"}']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('unas.jpg')],
        ]);

        $response->assertSessionHas('colorSuggestion');
        $sugerencia = $response->getSession()->get('colorSuggestion');

        $this->assertSame('rosa degradado a blanco', $sugerencia['name']);
        $this->assertSame('#F2D5D5', $sugerencia['hex']);

        // Y queda guardado ya, sin esperar a que ella confirme: si cierra la
        // hoja sin tocar nada, el color propuesto igual sirve.
        $upload = ContentUpload::query()->where('provider_id', $provider->id)->sole();
        $this->assertSame('rosa degradado a blanco', $upload->color_name);
        $this->assertSame('#F2D5D5', $upload->color_hex);
    }

    public function test_her_words_win_over_the_model(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"nombre": "rosa palo", "hex": "#F2D5D5"}']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('unas.jpg')],
        ]);

        $id = ContentUpload::query()->where('provider_id', $provider->id)->sole()->id;

        $this->actingAs($provider->user)
            ->patch('/admin/contenido/fotos/color', [
                'uploadIds' => [$id],
                'name' => 'nude con glitter',
                'hex' => '#e8d5c4',
            ])
            ->assertSessionHasNoErrors();

        $upload = ContentUpload::find($id);
        $this->assertSame('nude con glitter', $upload->color_name);
        // Guardado en mayúsculas, como lo devuelve el modelo, para que las
        // comparaciones no dependan de cómo lo escribió ella.
        $this->assertSame('#E8D5C4', $upload->color_hex);
    }

    public function test_a_provider_cannot_set_the_colour_of_another_providers_photo(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        $upload = ContentUpload::create([
            'provider_id' => $hers->id,
            'path' => "providers/{$hers->id}/gallery/x.webp",
            'kind' => 'image',
            'purpose' => 'edit',
        ]);

        $this->actingAs($mine->user)->patch('/admin/contenido/fotos/color', [
            'uploadIds' => [$upload->id],
            'name' => 'hackeado',
            'hex' => '#000000',
        ]);

        $this->assertNull($upload->fresh()->color_name);
    }

    public function test_a_broken_colour_answer_does_not_stop_the_upload(): void
    {
        // Si el modelo contesta cualquier cosa, la foto igual se sube: el
        // color es un extra, no un requisito.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'no tengo idea']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('unas.jpg')],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('colorSuggestion');
        $this->assertSame(1, ContentUpload::query()->where('provider_id', $provider->id)->count());
    }

    public function test_a_photo_that_already_has_a_design_on_it_gets_a_warning(): void
    {
        // Un flyer ya terminado —título, precio, contacto ya impresos—
        // subido como material crudo: el sistema le monta SU propio titular
        // arriba y queda ilegible. Visto en una generación real de Josean.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'SI']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('flyer.jpg')],
        ]);

        $response->assertSessionHas('warning', 'admin.contentPhotoAlreadyDesigned');
    }

    public function test_a_plain_work_photo_gets_no_warning(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'NO']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('trabajo.jpg')],
        ]);

        $response->assertSessionMissing('warning');
    }

    public function test_the_design_check_only_runs_on_the_first_batch(): void
    {
        // Las tandas siguientes son las mismas fotos partidas para
        // Cloudflare; preguntarle al modelo en cada una pagaría lo mismo
        // varias veces por la misma selección.
        Http::fake();

        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'first' => '0',
            'photos' => [UploadedFile::fake()->image('otra.jpg')],
        ]);

        Http::assertNothingSent();
    }

    public function test_the_design_check_does_not_run_on_references(): void
    {
        // Una referencia nunca arma un post: no hay nada de qué avisar.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'sugerencia genérica']]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('ref.jpg')],
        ]);

        $response->assertSessionMissing('warning');
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

    public function test_the_headline_comes_from_the_trade_library_not_from_the_model(): void
    {
        // El modelo contesta solo el pie del post. Antes también inventaba el
        // titular y salía «UÑAS DE HOY / GLOW STUDIOS» — el nombre del
        // negocio gastando una línea que el sello ya muestra.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => implode("\n", [
                'TITULAR: ESTE TITULAR | NO SE USA',
                'DESCRIPCION: Un texto cualquiera.',
                'HASHTAGS: #nails',
            ])]]],
        ])]);

        $provider = Provider::factory()->published()->create([
            'business_category' => 'nails',
            'content_style' => ['colores' => ['#111827', '#e11d63'], 'lleva_precio' => false],
        ]);

        $written = app(WriteCaption::class)->handle($provider);

        $this->assertCount(2, $written['headline']);
        $this->assertContains(
            $written['headline'],
            HeadlinePhrases::forCategory(BusinessCategory::Nails),
            'El titular no salió de la biblioteca del rubro.',
        );
    }

    public function test_with_a_price_the_headline_uses_one_of_her_real_services(): void
    {
        // Nunca uno inventado: sale de su propia lista de servicios.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => "DESCRIPCION: texto\nHASHTAGS: #nails"]]],
        ])]);

        $provider = Provider::factory()->published()->create([
            'business_category' => 'nails',
            'content_style' => ['colores' => ['#111827', '#e11d63'], 'lleva_precio' => true],
        ]);

        $provider->services()->create([
            'name' => 'Acrílicas',
            'price' => 65,
            'duration_minutes' => 90,
            'category' => 'nails',
            'position' => 1,
            'is_active' => true,
        ]);

        $written = app(WriteCaption::class)->handle($provider);

        $this->assertSame(['ACRÍLICAS', 'desde 65'], $written['headline']);
    }

    public function test_the_headline_survives_a_model_that_never_answers(): void
    {
        // No lo escribe el modelo, así que un fallo de red no puede dejar el
        // post sin nada encima — que es como quedaba antes.
        Http::fake(['*' => Http::response([], 500)]);

        $provider = Provider::factory()->published()->create([
            'business_category' => 'hair',
            'content_style' => ['colores' => ['#111827', '#e11d63'], 'lleva_precio' => false],
        ]);

        $written = app(WriteCaption::class)->handle($provider);

        $this->assertCount(2, $written['headline']);
    }

    public function test_generating_with_a_template_reads_and_caches_that_references_style(): void
    {
        // Ella lo pidió directo: "¿por qué no ofrece: mira, están estas
        // plantillas disponibles, cómo lo quiere?" — en vez de mezclar todas
        // sus referencias en un estilo promedio, elegir UNA puntual.
        Http::fake(['*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'colores' => ['#0b0f19', '#c9a227', '#ffffff'],
                'posicion_texto' => 'centro',
                'tipografia' => 'condensada',
                'estilo_titular' => 'resaltado',
                'lleva_precio' => false,
            ])]]]])
            ->push(['choices' => [['message' => ['content' => implode("\n", [
                'TITULAR: CITAS ABIERTAS | ESTA SEMANA',
                'DESCRIPCION: Un texto cualquiera.',
                'HASHTAGS: #hair #beauty',
            ])]]]])]);

        $provider = Provider::factory()->published()->create();

        $template = ContentUpload::create([
            'provider_id' => $provider->id,
            'path' => "providers/{$provider->id}/gallery/ref.webp",
            'kind' => 'image',
            'purpose' => 'reference',
        ]);

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', [
                'layout' => 'collage',
                'uploadIds' => $ids,
                'templateUploadId' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('resaltado', $template->fresh()->learned_style['estilo_titular']);
        $this->assertNotNull($template->fresh()->learned_style_at);
        Http::assertSentCount(2);
    }

    public function test_a_cached_template_style_is_not_read_twice(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'colores' => ['#0b0f19', '#c9a227', '#ffffff'],
                'estilo_titular' => 'limpio',
            ])]]]])
            ->whenEmpty(Http::response(['choices' => [['message' => ['content' => implode("\n", [
                'TITULAR: A | B',
                'DESCRIPCION: texto',
                'HASHTAGS: #a',
            ])]]]]))]);

        $provider = Provider::factory()->published()->create();

        $template = ContentUpload::create([
            'provider_id' => $provider->id,
            'path' => "providers/{$provider->id}/gallery/ref.webp",
            'kind' => 'image',
            'purpose' => 'reference',
        ]);

        foreach (range(1, 2) as $tanda) {
            $this->actingAs($provider->user)->post('/admin/contenido/subir', [
                'purpose' => 'edit',
                'photos' => [UploadedFile::fake()->image("f{$tanda}.jpg")],
            ]);
        }

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        foreach ($ids as $id) {
            $this->actingAs($provider->user)->post('/admin/contenido/generar', [
                'layout' => 'hero',
                'uploadIds' => [$id],
                'templateUploadId' => $template->id,
            ]);
        }

        // Una sola lectura de la ficha (la primera vez) + una descripción por
        // cada post armado: pagar la visión de nuevo por la misma foto sería
        // tirar la plata.
        Http::assertSentCount(3);
    }

    public function test_a_template_from_another_provider_is_ignored(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => implode("\n", [
            'TITULAR: A | B',
            'DESCRIPCION: texto',
            'HASHTAGS: #a',
        ])]]]])]);

        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        $herTemplate = ContentUpload::create([
            'provider_id' => $hers->id,
            'path' => "providers/{$hers->id}/gallery/ref.webp",
            'kind' => 'image',
            'purpose' => 'reference',
        ]);

        $this->actingAs($mine->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => [UploadedFile::fake()->image('f.jpg')],
        ]);

        $ids = ContentUpload::query()->where('provider_id', $mine->id)->waiting()->pluck('id')->all();

        $this->actingAs($mine->user)
            ->post('/admin/contenido/generar', [
                'layout' => 'hero',
                'uploadIds' => $ids,
                'templateUploadId' => $herTemplate->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($herTemplate->fresh()->learned_style);
        // Solo la del caption: la ficha ajena ni se intenta leer.
        Http::assertSentCount(1);
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

    public function test_an_empty_first_answer_is_retried_instead_of_falling_back(): void
    {
        // Un post real de Josean (30-ago): el modelo gastó todo el
        // presupuesto de tokens "pensando" y no dejó nada para la respuesta
        // visible — quedó un post con el texto de repuesto genérico y sin
        // hashtags, mientras que el post de un minuto antes y el de un
        // minuto después salieron perfectos. Un solo reintento alcanza.
        Http::fake(['*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => '']]]])
            ->push(['choices' => [['message' => ['content' => implode("\n", [
                'TITULAR: BALAYAGE | 120',
                'DESCRIPCION: Hoy me quedé enamorada de este balayage.',
                'HASHTAGS: #hair #balayage',
            ])]]]])]);

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

        $this->assertSame('Hoy me quedé enamorada de este balayage.', $post->caption);
        $this->assertSame(['#hair', '#balayage'], $post->hashtags);
    }

    public function test_two_empty_answers_in_a_row_fall_back_instead_of_looping(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '']]]])]);

        $provider = Provider::factory()->published()->create(['public_name' => 'Josean']);

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->assertStringContainsString('Josean — nuevo trabajo', $post->caption);
        Http::assertSentCount(2);
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

    public function test_a_carousel_is_a_cover_plus_one_clean_photo_per_slide(): void
    {
        // La receta que ella mostró con un ejemplo: «una portada con letras
        // bonitas, buena frase, seguido de fotos». Antes el medio eran
        // collages de cuatro fotitos con el pie de contacto encima, que a esa
        // altura del carrusel solo tapan el trabajo.
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

        // Seis fotos = una portada + las cinco restantes, una por lámina.
        $this->assertCount(6, $post->slides);
        $this->assertSame($post->slides[0], $post->path);

        foreach ($post->slides as $slide) {
            Storage::disk('r2')->assertExists($slide);
        }
    }

    public function test_a_carousel_never_goes_past_what_instagram_accepts(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 10))->map(fn () => UploadedFile::fake()->image('x.jpg'))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'carousel', 'uploadIds' => $ids]);

        $this->assertLessThanOrEqual(
            10,
            count(ContentPost::query()->where('provider_id', $provider->id)->sole()->slides),
        );
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

    public function test_learning_style_reads_video_references_too(): void
    {
        // Antes esta parte quedaba completamente ciega: un video de
        // referencia se guardaba pero nunca se miraba. Ella lo notó primero:
        // «siento que no está leyendo esa parte del video [...] no agrega ese
        // tipo de letra».
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'colores' => ['#0b0f19', '#c9a227', '#ffffff'],
                'posicion_texto' => 'centro',
                'tipografia' => 'condensada',
                'estilo_titular' => 'cursiva',
                'lleva_precio' => false,
            ])]]],
        ])]);

        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/subir', [
                'purpose' => 'reference',
                // El clip sintético es tan mínimo (un segundo, sin audio) que
                // libmagic a veces lo clasifica como "application/mp4" en vez
                // de "video/mp4" al no tener extensión en el archivo
                // temporal — se fuerza el tipo real para no probar una
                // ambigüedad de fixture en vez del código.
                'photos' => [UploadedFile::fake()->createWithContent('tendencia.mp4', $this->unVideoDePrueba())->mimeType('video/mp4')],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/estilo')
            ->assertSessionHasNoErrors();

        $this->assertSame('cursiva', $provider->fresh()->content_style['estilo_titular']);
    }

    /**
     * Un clip real y chiquito, generado en el momento: la prueba necesita
     * bytes de video de verdad para que ffmpeg le saque un fotograma, no un
     * archivo relleno como el que usan las pruebas de solo-guardado.
     */
    private function unVideoDePrueba(): string
    {
        $out = sys_get_temp_dir().'/glow-test-clip-'.uniqid().'.mp4';

        Process::timeout(20)->run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'color=c=blue:s=64x64:d=1',
            $out,
        ]);

        return file_get_contents($out);
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

    public function test_no_note_suggestion_is_asked_when_she_already_wrote_one(): void
    {
        // Si ella ya escribió la nota, no hay nada que sugerir ahí — pero el
        // estilo se sigue leyendo solo, que es una cosa aparte.
        Http::fake();

        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'note' => 'Ya lo sé, me gusta así.',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $response->assertSessionMissing('noteSuggestion');
        Http::assertSentCount(1);
    }

    public function test_uploading_the_first_reference_batch_learns_the_style_on_its_own(): void
    {
        // Antes había que acordarse de tocar un botón aparte después de
        // subir. Ella lo dijo directo: «apenas se suba algo como referencia
        // tiene que detectarlo».
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'colores' => ['#0b0f19', '#c9a227', '#ffffff'],
                'posicion_texto' => 'centro',
                'tipografia' => 'condensada',
                'estilo_titular' => 'resaltado',
                'lleva_precio' => false,
            ])]]],
        ])]);

        $provider = Provider::factory()->published()->create();
        $this->assertNull($provider->content_style);

        // Con nota ya puesta, para que la única llamada sea la del estilo.
        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'reference',
            'note' => 'algo',
            'first' => '1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('resaltado', $provider->fresh()->content_style['estilo_titular']);
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

    public function test_only_the_ten_most_recent_photos_are_shown(): void
    {
        // Con todas, la cuadrícula ocupaba cinco filas de miniaturas
        // diminutas y empujaba el resto de la pantalla fuera de vista. Ella
        // lo pidió mirándolo: «con 10 cuadrículas es suficiente».
        $provider = Provider::factory()->published()->create();

        foreach (range(1, 14) as $n) {
            ContentUpload::create([
                'provider_id' => $provider->id,
                'path' => "providers/{$provider->id}/gallery/f{$n}.webp",
                'kind' => 'image',
                'purpose' => 'edit',
            ]);
        }

        $this->actingAs($provider->user)->get('/admin/contenido')->assertInertia(
            fn (Assert $page) => $page->has('recentEdits', 10),
        );
    }

    public function test_a_photo_can_be_put_into_and_taken_out_of_the_queue(): void
    {
        // Antes solo se podía METER: las fotos que ya estaban en la cola no
        // eran tocables, y ella se quedó trabada — «yo la selecciono, voy
        // agregando, y después ya no la puedo deseleccionar».
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids]);

        $this->assertSame(0, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());

        // Un toque la mete de nuevo…
        $this->actingAs($provider->user)
            ->patch("/admin/contenido/fotos/{$ids[0]}/cola")
            ->assertSessionHasNoErrors();

        $this->assertNull(ContentUpload::find($ids[0])->used_at);
        $this->assertNotNull(ContentUpload::find($ids[1])->used_at);

        // …y otro la vuelve a sacar, que es lo que faltaba.
        $this->actingAs($provider->user)
            ->patch("/admin/contenido/fotos/{$ids[0]}/cola")
            ->assertSessionHasNoErrors();

        $this->assertNotNull(ContentUpload::find($ids[0])->used_at);
        $this->assertSame(0, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());
    }

    public function test_taking_a_never_used_photo_out_of_the_queue_works_too(): void
    {
        // Recién subida y todavía sin usar: sacarla tiene que poder hacerse
        // igual, que es el caso de «voy agregando y me arrepiento».
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => [UploadedFile::fake()->image('f.jpg')],
        ]);

        $id = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->sole()->id;

        $this->actingAs($provider->user)
            ->patch("/admin/contenido/fotos/{$id}/cola")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContentUpload::query()->where('provider_id', $provider->id)->waiting()->count());
    }

    public function test_a_provider_cannot_touch_another_providers_photo_queue(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();

        $upload = ContentUpload::create([
            'provider_id' => $hers->id,
            'path' => 'providers/2/gallery/x.webp',
            'kind' => 'image',
            'purpose' => 'edit',
            'used_at' => now(),
        ]);

        $this->actingAs($mine->user)
            ->patch("/admin/contenido/fotos/{$upload->id}/cola")
            ->assertForbidden();

        $this->assertNotNull($upload->fresh()->used_at);
    }

    public function test_a_downvote_frees_the_photos_to_try_again(): void
    {
        // Antes, un post que no le gustó dejaba las mismas fotos marcadas
        // como usadas para siempre — para volver a intentar tenía que
        // subirlas de nuevo desde el teléfono. Ella lo señaló directo: «esa
        // foto deberían quedar ahí lista, para volverlas a seleccionar».
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids]);

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();
        $this->assertSame(0, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());

        $this->actingAs($provider->user)
            ->patch("/admin/contenido/{$post->id}/calificar", ['rating' => 'down', 'note' => 'No me gustó el recorte.'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'admin.contentRatedDownFreedPhotos');

        $this->assertSame(2, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());
    }

    public function test_an_upvote_does_not_free_the_photos(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids]);

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->actingAs($provider->user)
            ->patch("/admin/contenido/{$post->id}/calificar", ['rating' => 'up']);

        $this->assertSame(0, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());
    }

    public function test_downvoting_twice_does_not_steal_photos_a_new_post_already_reused(): void
    {
        // Sin esta protección: rechazar A libera sus fotos, ella las usa
        // para armar B, y si vuelve a guardar el motivo de A (mismo "no me
        // gusta" de antes), esas fotos se le soltarían de B por segunda vez.
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/contenido/subir', [
            'purpose' => 'edit',
            'photos' => collect(range(1, 2))->map(fn (int $n) => UploadedFile::fake()->image("f{$n}.jpg"))->all(),
        ]);

        $ids = ContentUpload::query()->where('provider_id', $provider->id)->waiting()->pluck('id')->all();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids]);

        $postA = ContentPost::query()->where('provider_id', $provider->id)->sole();

        $this->actingAs($provider->user)
            ->patch("/admin/contenido/{$postA->id}/calificar", ['rating' => 'down', 'note' => 'primer intento']);

        // Las mismas fotos, reusadas en un post B.
        $this->actingAs($provider->user)
            ->post('/admin/contenido/generar', ['layout' => 'collage', 'uploadIds' => $ids]);

        $this->assertSame(0, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());

        // Vuelve a guardar el "no me gusta" de A (edita el motivo, por ejemplo).
        $this->actingAs($provider->user)
            ->patch("/admin/contenido/{$postA->id}/calificar", ['rating' => 'down', 'note' => 'motivo editado']);

        // Las fotos de B siguen usadas: no se las robó el segundo guardado de A.
        $this->assertSame(0, ContentUpload::query()->whereIn('id', $ids)->waiting()->count());
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
