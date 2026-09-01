<?php

namespace Tests\Feature\Admin;

use App\Models\ContentAsset;
use App\Models\ContentPost;
use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * Armar el post a mano, eligiendo las piezas.
 *
 * Es la salida al único dato que la app no puede sacar de la foto: si es el
 * antes, el proceso o el resultado. Ella lo dijo mirando posts reales — «al
 * otro le pones proceso y de repente no, ya eso es terminado».
 */
class ComposeChosenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => "DESCRIPCION: texto\nHASHTAGS: #nails"]]],
        ])]);
    }

    private function asset(string $slug, string $color = '#101010'): ContentAsset
    {
        $path = 'pack/frase/'.$slug.'-'.uniqid().'.png';

        $arte = ImageManager::imagick()->create(1080, 1080)->fill('rgba(0,0,0,0)');
        $arte->drawRectangle(240, 480, function ($r) use ($color): void {
            $r->size(600, 120);
            $r->background($color);
        });

        Storage::disk('r2')->put($path, (string) $arte->toPng());

        return ContentAsset::create([
            'kind' => 'frase', 'path' => $path, 'ink' => 'dark', 'slug' => $slug,
            'text' => $slug, 'box_x' => 240, 'box_y' => 480, 'box_w' => 600, 'box_h' => 120,
        ]);
    }

    private function foto(Provider $provider): ContentUpload
    {
        $path = "providers/{$provider->id}/gallery/".uniqid().'.jpg';
        Storage::disk('r2')->put($path, (string) ImageManager::imagick()->create(1080, 1080)->fill('#8a8a8a')->toJpeg());

        return ContentUpload::create([
            'provider_id' => $provider->id, 'path' => $path, 'kind' => 'image', 'purpose' => 'edit',
        ]);
    }

    public function test_she_can_stamp_a_moment_phrase_the_system_would_never_choose(): void
    {
        // "proceso" es justo la que el automático tiene prohibida, porque no
        // sabe si la foto es el proceso. Ella sí.
        $provider = Provider::factory()->published()->create();
        $foto = $this->foto($provider);
        $proceso = $this->asset('proceso');

        $this->actingAs($provider->user)
            ->post('/admin/contenido/componer', ['uploadId' => $foto->id, 'assetIds' => [$proceso->id]])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();
        Storage::disk('r2')->assertExists($post->path);

        // Y la foto queda marcada como usada, igual que en el automático.
        $this->assertNotNull($foto->fresh()->used_at);
    }

    public function test_the_stickers_are_stacked_in_the_order_she_tapped_them(): void
    {
        $provider = Provider::factory()->published()->create();
        $foto = $this->foto($provider);

        // La segunda tapa a la primera: la última que tocó queda arriba.
        $abajo = $this->asset('antes', '#101010');
        $arriba = $this->asset('despues', '#ff00aa');

        $this->actingAs($provider->user)
            ->post('/admin/contenido/componer', [
                'uploadId' => $foto->id,
                'assetIds' => [$abajo->id, $arriba->id],
            ])
            ->assertSessionHasNoErrors();

        $post = ContentPost::query()->where('provider_id', $provider->id)->sole();
        $imagen = ImageManager::imagick()->read(Storage::disk('r2')->get($post->path));

        $this->assertSame('ff00aa', strtolower($imagen->pickColor(540, 540)->toHex()));
    }

    public function test_a_broken_pack_piece_is_refused(): void
    {
        // "Traicional" viene mal escrita del pack: no la pone ni el sistema
        // ni ella.
        $provider = Provider::factory()->published()->create();
        $foto = $this->foto($provider);
        $rota = $this->asset('traicional');

        $this->actingAs($provider->user)
            ->post('/admin/contenido/componer', ['uploadId' => $foto->id, 'assetIds' => [$rota->id]])
            ->assertSessionHasErrors('assetIds');

        $this->assertSame(0, ContentPost::query()->count());
    }

    public function test_a_provider_cannot_compose_over_another_providers_photo(): void
    {
        $mine = Provider::factory()->published()->create();
        $hers = Provider::factory()->published()->create();
        $suya = $this->foto($hers);
        $pieza = $this->asset('proceso');

        $this->actingAs($mine->user)
            ->post('/admin/contenido/componer', ['uploadId' => $suya->id, 'assetIds' => [$pieza->id]])
            ->assertSessionHasErrors('uploadId');

        $this->assertSame(0, ContentPost::query()->count());
        $this->assertNull($suya->fresh()->used_at);
    }

    public function test_the_trays_offer_moment_phrases_that_the_automatic_never_uses(): void
    {
        $provider = Provider::factory()->published()->create();
        $this->asset('proceso');
        $this->asset('agenda-abierta');

        $this->actingAs($provider->user)->get('/admin/contenido')->assertInertia(
            fn ($page) => $page->has('trays')
        );

        $bandejas = app(\App\Actions\Content\StickerTrays::class)->handle($provider);
        $textos = collect($bandejas)->flatMap(fn (array $b): array => array_column($b['items'], 'text'));

        $this->assertTrue($textos->contains('proceso'), 'La bandeja debería ofrecer "proceso".');
        $this->assertTrue($textos->contains('agenda-abierta'));
    }
}
