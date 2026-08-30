<?php

namespace Tests\Unit;

use App\Actions\Content\FindOrCreateColorProp;
use App\Enums\BusinessCategory;
use App\Models\ColorProp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La foto decorativa a juego con un color —el limón de "Butter yellow" que
 * ella trajo de Mimosa Studio— cacheada por rubro y por color del catálogo,
 * nunca generada dos veces para lo mismo.
 */
class FindOrCreateColorPropTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
    }

    public function test_it_generates_once_and_reuses_the_second_time(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['images' => [['image_url' => ['url' => 'data:image/png;base64,'.self::PNG_1X1]]]]]],
        ])]);

        $props = app(FindOrCreateColorProp::class);

        $primero = $props->handle(BusinessCategory::Nails, 'rosa degradado a blanco', '#F2D5D5');
        $segundo = $props->handle(BusinessCategory::Nails, 'rosa pastel', '#F0DDD5');

        $this->assertNotNull($primero);
        // "rosa degradado a blanco" y "rosa pastel" caen en el mismo cajón
        // del catálogo (Rosa Susurro / Whisper Pink), así que la segunda
        // llamada reusa la misma foto en vez de generar otra.
        $this->assertSame($primero, $segundo);

        Http::assertSentCount(1);
        $this->assertSame(1, ColorProp::query()->count());
    }

    public function test_a_different_business_category_gets_its_own_prop(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['images' => [['image_url' => ['url' => 'data:image/png;base64,'.self::PNG_1X1]]]]]],
        ])]);

        $props = app(FindOrCreateColorProp::class);

        $props->handle(BusinessCategory::Nails, 'rosa', '#F2D5D5');
        $props->handle(BusinessCategory::Hair, 'rosa', '#F2D5D5');

        Http::assertSentCount(2);
        $this->assertSame(2, ColorProp::query()->count());
    }

    public function test_a_failed_generation_returns_null_and_caches_nothing(): void
    {
        // Sin la foto decorativa, el post se arma igual: la esquina se deja
        // como fondo liso. Nunca bloquea nada.
        Http::fake(['*' => Http::response([], 500)]);

        $result = app(FindOrCreateColorProp::class)->handle(BusinessCategory::Nails, 'rosa', '#F2D5D5');

        $this->assertNull($result);
        $this->assertSame(0, ColorProp::query()->count());
    }
}
