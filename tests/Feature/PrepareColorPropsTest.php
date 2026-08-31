<?php

namespace Tests\Feature;

use App\Models\ColorProp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Generar por adelantado toda la biblioteca de decorativas de un rubro, para
 * pagarla de una sola vez y no ir goteando el gasto post a post.
 */
class PrepareColorPropsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['images' => [['image_url' => ['url' => 'data:image/png;base64,'.self::PNG_1X1]]]]]],
        ])]);
    }

    public function test_it_fills_the_whole_catalogue_for_one_trade(): void
    {
        $this->artisan('content:preparar-decorados', ['rubro' => 'nails'])
            ->expectsConfirmation('Se van a generar hasta 26 fotos decorativas para nails. ¿Seguir?', 'yes')
            ->assertSuccessful();

        $this->assertSame(26, ColorProp::query()->where('business_category', 'nails')->count());
        // Un rubro no le roba el trabajo a otro: cada uno tiene su biblioteca.
        $this->assertSame(0, ColorProp::query()->where('business_category', 'hair')->count());
    }

    public function test_it_does_not_pay_twice_for_what_it_already_has(): void
    {
        $this->artisan('content:preparar-decorados', ['rubro' => 'nails'])
            ->expectsConfirmation('Se van a generar hasta 26 fotos decorativas para nails. ¿Seguir?', 'yes')
            ->assertSuccessful();

        Http::fake(['*' => Http::response([], 500)]);

        // Segunda pasada: no queda nada por hacer, así que ni pregunta ni
        // llama al modelo — que es justamente el punto del caché.
        $this->artisan('content:preparar-decorados', ['rubro' => 'nails'])
            ->expectsOutputToContain('Nada que hacer')
            ->assertSuccessful();

        $this->assertSame(26, ColorProp::query()->where('business_category', 'nails')->count());
    }

    public function test_an_unknown_trade_is_refused(): void
    {
        $this->artisan('content:preparar-decorados', ['rubro' => 'pasteleria'])
            ->assertFailed();

        $this->assertSame(0, ColorProp::query()->count());
    }

    public function test_declining_the_confirmation_spends_nothing(): void
    {
        $this->artisan('content:preparar-decorados', ['rubro' => 'nails'])
            ->expectsConfirmation('Se van a generar hasta 26 fotos decorativas para nails. ¿Seguir?', 'no')
            ->assertSuccessful();

        $this->assertSame(0, ColorProp::query()->count());
        Http::assertNothingSent();
    }
}
