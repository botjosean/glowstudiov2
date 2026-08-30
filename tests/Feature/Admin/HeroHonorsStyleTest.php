<?php

namespace Tests\Feature\Admin;

use App\Actions\Content\BuildHero;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * El formato de foto grande tiene que respetar la ficha de estilo igual que
 * el collage.
 *
 * Reproduce el fallo que ella reportó el 30-ago con tres capturas seguidas:
 * eligió una plantilla, el sistema la leyó bien, y los tres posts salieron
 * idénticos —serif blanca abajo a la izquierda— porque BuildHero tenía su
 * propio dibujado escrito a mano que ignoraba la ficha entera. «Sí agarra la
 * foto de referencia, sí hace todo, pero cuando me da el resultado no es
 * nada parecido [...] no son las letras.»
 */
class HeroHonorsStyleTest extends TestCase
{
    use RefreshDatabase;

    private function unaFotoEnR2(Provider $provider): string
    {
        Storage::fake('r2');

        $path = "providers/{$provider->id}/gallery/foto.jpg";
        Storage::disk('r2')->put($path, UploadedFile::fake()->image('foto.jpg', 1200, 1200)->get());

        return $path;
    }

    public function test_the_learned_palette_shows_up_in_a_hero_post(): void
    {
        $provider = Provider::factory()->published()->create();
        $path = $this->unaFotoEnR2($provider);

        // Una ficha que pide bloques con un magenta imposible de confundir
        // con la foto gris de prueba.
        $provider->content_style = [
            'colores' => ['#ff00aa', '#00ffcc', '#ff00aa'],
            'estilo_titular' => 'bloques',
            'posicion_texto' => 'centro',
            'tipografia' => 'condensada',
        ];

        $key = app(BuildHero::class)->handle($provider, [$path], ['PALABRA']);

        $canvas = ImageManager::imagick()->read(Storage::disk('r2')->get($key));

        $encontrado = false;

        // Barrido por la banda central, donde la ficha pidió el titular.
        for ($x = 100; $x < 980 && ! $encontrado; $x += 6) {
            for ($y = 480; $y < 600; $y += 6) {
                if (strtolower($canvas->pickColor($x, $y)->toHex()) === 'ff00aa') {
                    $encontrado = true;

                    break;
                }
            }
        }

        $this->assertTrue($encontrado, 'El titular de la foto grande no usó el color de la plantilla.');
    }

    public function test_two_different_style_cards_do_not_produce_the_same_hero(): void
    {
        // Antes daban byte por byte lo mismo: la ficha ni se miraba.
        $provider = Provider::factory()->published()->create();
        $path = $this->unaFotoEnR2($provider);

        $hero = app(BuildHero::class);

        $provider->content_style = [
            'colores' => ['#ff00aa', '#00ffcc', '#ff00aa'],
            'estilo_titular' => 'bloques',
            'posicion_texto' => 'centro',
            'tipografia' => 'condensada',
        ];
        $unKey = $hero->handle($provider, [$path], ['PALABRA']);

        $provider->content_style = [
            'colores' => ['#101010', '#f5f5f5', '#101010'],
            'estilo_titular' => 'limpio',
            'posicion_texto' => 'arriba',
            'tipografia' => 'serif',
        ];
        $otroKey = $hero->handle($provider, [$path], ['PALABRA']);

        $this->assertNotSame(
            Storage::disk('r2')->get($unKey),
            Storage::disk('r2')->get($otroKey),
            'Dos fichas distintas dieron exactamente el mismo post.',
        );
    }
}
