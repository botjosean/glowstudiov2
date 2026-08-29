<?php

namespace Tests\Feature\Admin;

use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guardar una referencia desde un enlace pegado.
 *
 * Lo que se prueba acá no es sobre todo que traiga la foto: es que NO traiga
 * lo que no debe. Es el servidor quien sale a internet, así que un enlace a
 * una dirección interna sería una puerta abierta, no una foto fea.
 */
class ContentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
    }

    private function pngBytes(): string
    {
        $img = imagecreatetruecolor(40, 40);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_an_image_link_is_saved_as_a_reference(): void
    {
        $provider = Provider::factory()->published()->create();

        Http::fake(['*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs($provider->user)
            ->post('/admin/contenido/enlace', [
                'url' => 'https://ejemplo.com/foto.png',
                'note' => 'Me gusta el fondo limpio.',
            ])
            ->assertSessionHasNoErrors();

        $upload = ContentUpload::query()->where('provider_id', $provider->id)->references()->sole();

        $this->assertSame('Me gusta el fondo limpio.', $upload->note);
        Storage::disk('r2')->assertExists($upload->path);
    }

    public static function direccionesInternas(): array
    {
        return [
            'localhost' => ['http://localhost/foto.png'],
            'loopback' => ['http://127.0.0.1/foto.png'],
            'red privada' => ['http://192.168.1.10/foto.png'],
            'otra privada' => ['http://10.0.0.5/foto.png'],
            // La dirección de metadatos de la nube: la joya de este ataque.
            'metadatos de la nube' => ['http://169.254.169.254/latest/meta-data/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('direccionesInternas')]
    public function test_an_internal_address_is_refused_without_any_request(string $url): void
    {
        $provider = Provider::factory()->published()->create();

        Http::fake();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/enlace', ['url' => $url])
            ->assertSessionHasErrors('url');

        // No basta con no guardarla: el servidor no puede ni haber preguntado,
        // porque el propio intento ya revela qué hay del otro lado.
        Http::assertNothingSent();
        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_a_non_http_scheme_is_refused(): void
    {
        $provider = Provider::factory()->published()->create();

        Http::fake();

        $this->actingAs($provider->user)
            ->post('/admin/contenido/enlace', ['url' => 'file:///etc/passwd'])
            ->assertSessionHasErrors('url');

        Http::assertNothingSent();
    }

    public function test_a_link_that_is_not_an_image_is_refused(): void
    {
        $provider = Provider::factory()->published()->create();

        Http::fake(['*' => Http::response('<html>hola</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs($provider->user)
            ->post('/admin/contenido/enlace', ['url' => 'https://ejemplo.com/pagina'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_a_content_type_that_lies_is_caught_by_the_bytes(): void
    {
        // El otro servidor dice "image/png" y manda texto. Manda el archivo.
        $provider = Provider::factory()->published()->create();

        Http::fake(['*' => Http::response('esto no es una imagen', 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs($provider->user)
            ->post('/admin/contenido/enlace', ['url' => 'https://ejemplo.com/mentira.png'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_redirects_are_not_followed(): void
    {
        // Una redirección puede llevar a una dirección interna DESPUÉS de que
        // el nombre original pasó el control.
        $provider = Provider::factory()->published()->create();

        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

        $this->actingAs($provider->user)
            ->post('/admin/contenido/enlace', ['url' => 'https://ejemplo.com/redirige'])
            ->assertSessionHasErrors('url');

        Http::assertSentCount(1);
        $this->assertSame(0, ContentUpload::query()->count());
    }

    public function test_a_guest_cannot_make_the_server_fetch_anything(): void
    {
        Http::fake();

        $this->post('/admin/contenido/enlace', ['url' => 'https://ejemplo.com/foto.png'])
            ->assertRedirect('/iniciar-sesion');

        Http::assertNothingSent();
    }
}
