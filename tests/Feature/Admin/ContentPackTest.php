<?php

namespace Tests\Feature\Admin;

use App\Actions\Content\BuildHero;
use App\Actions\Content\PickAsset;
use App\Actions\Content\StampAsset;
use App\Enums\BusinessCategory;
use App\Models\ContentAsset;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * El pack de contenido que ella compró: frases con letra de diseñador que se
 * estampan en vez de dibujarse con tipografías.
 *
 * Es el cambio que cierra un reclamo que repitió post tras post — «no son
 * las letras, no logras llegar al punto»— y que no se resolvía puliendo el
 * dibujado, porque estas frases las diseñó una persona.
 */
class ContentPackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
    }

    /** Una frase de prueba: una mancha del color pedido, centrada. */
    private function asset(string $ink, ?string $trade = null, ?string $slug = null): ContentAsset
    {
        $color = $ink === 'dark' ? '#101010' : '#f5f5f5';
        $path = 'pack/frase/'.$ink.'-'.($trade ?? 'general').'-'.($slug ?? 'x').'-'.uniqid().'.png';

        $manager = ImageManager::imagick();
        $arte = $manager->create(1080, 1080)->fill('rgba(0,0,0,0)');
        $arte->drawRectangle(240, 480, function ($r) use ($color): void {
            $r->size(600, 120);
            $r->background($color);
        });

        Storage::disk('r2')->put($path, (string) $arte->toPng());

        return ContentAsset::create([
            'kind' => 'frase', 'trade' => $trade, 'path' => $path, 'ink' => $ink,
            'slug' => $slug, 'box_x' => 240, 'box_y' => 480, 'box_w' => 600, 'box_h' => 120,
        ]);
    }

    private function foto(Provider $provider, string $color): string
    {
        $path = "providers/{$provider->id}/gallery/".uniqid().'.jpg';
        Storage::disk('r2')->put($path, (string) ImageManager::imagick()->create(1080, 1080)->fill($color)->toJpeg());

        return $path;
    }

    public function test_a_dark_photo_gets_the_light_lettering(): void
    {
        // Es el detalle que hace que el titular se lea igual sobre unas uñas
        // blancas que sobre una mesa negra, sin ensuciar la foto con sombra.
        $this->asset('dark');
        $clara = $this->asset('light');

        $provider = Provider::factory()->published()->create();
        $canvas = ImageManager::imagick()->create(1080, 1080)->fill('#141414');

        $elegida = app(StampAsset::class)->pick($canvas, app(PickAsset::class)->phrases($provider), 'center');

        $this->assertSame($clara->id, $elegida->id);
    }

    public function test_a_bright_photo_gets_the_dark_lettering(): void
    {
        $oscura = $this->asset('dark');
        $this->asset('light');

        $provider = Provider::factory()->published()->create();
        $canvas = ImageManager::imagick()->create(1080, 1080)->fill('#f2f2f2');

        $elegida = app(StampAsset::class)->pick($canvas, app(PickAsset::class)->phrases($provider), 'center');

        $this->assertSame($oscura->id, $elegida->id);
    }

    public function test_the_service_phrase_wins_over_the_generic_ones(): void
    {
        // La app ya sabe que el trabajo fue de acrílicas y el pack trae
        // "Acrílicas" escrito por un diseñador: estampar la que corresponde
        // no es azar, es decir la verdad con letra linda.
        $this->asset('dark');
        $this->asset('light');
        $delServicio = $this->asset('dark', 'nails', 'acrilicas');

        $provider = Provider::factory()->published()->create(['business_category' => BusinessCategory::Nails]);

        $candidatas = app(PickAsset::class)->phrases($provider, 'acrilicas');

        $this->assertCount(1, $candidatas);
        $this->assertSame($delServicio->id, $candidatas->first()->id);
    }

    public function test_another_trades_phrases_are_never_offered(): void
    {
        $this->asset('dark', 'hair');
        $general = $this->asset('dark');

        $provider = Provider::factory()->published()->create(['business_category' => BusinessCategory::Nails]);

        $candidatas = app(PickAsset::class)->phrases($provider);

        $this->assertCount(1, $candidatas);
        $this->assertSame($general->id, $candidatas->first()->id);
    }

    public function test_a_hero_post_stamps_the_pack_instead_of_drawing_type(): void
    {
        $this->asset('dark');
        $this->asset('light');

        $provider = Provider::factory()->published()->create(['business_category' => BusinessCategory::Nails]);
        $foto = $this->foto($provider, '#8a8a8a');

        $key = app(BuildHero::class)->handle($provider, [$foto], ['LO QUE SEA']);

        Storage::disk('r2')->assertExists($key);

        // El lienzo dejó de ser un gris liso: algo se estampó encima.
        $post = ImageManager::imagick()->read(Storage::disk('r2')->get($key));
        $distintos = 0;

        for ($x = 100; $x < 980; $x += 20) {
            for ($y = 480; $y < 620; $y += 20) {
                if (strtolower($post->pickColor($x, $y)->toHex()) !== '8a8a8a') {
                    $distintos++;
                }
            }
        }

        $this->assertGreaterThan(0, $distintos, 'No se estampó ninguna frase del pack.');
    }

    public function test_without_a_pack_the_old_engine_still_runs(): void
    {
        // Los rubros que el pack no cubre siguen con las tipografías: el
        // motor viejo no se borró, cambió de papel.
        $provider = Provider::factory()->published()->create(['business_category' => BusinessCategory::Nails]);
        $foto = $this->foto($provider, '#8a8a8a');

        $this->assertFalse(app(PickAsset::class)->hasPack($provider));

        $key = app(BuildHero::class)->handle($provider, [$foto], ['SIGUE ANDANDO']);

        Storage::disk('r2')->assertExists($key);
    }
}
