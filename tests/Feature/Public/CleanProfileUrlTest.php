<?php

namespace Tests\Feature\Public;

use App\Models\Provider;
use App\Support\ReservedSlugs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CleanProfileUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_profile_lives_at_the_root(): void
    {
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->get('/pati')->assertOk();
        $this->assertSame(url('/pati'), route('providers.show', $provider));
    }

    /**
     * Every link already out in the world — sent over WhatsApp, published on
     * glowstudios.vip — points at the old shape. It has to keep working.
     */
    public function test_the_old_p_url_redirects_permanently(): void
    {
        Provider::factory()->published()->create(['slug' => 'pati']);

        $this->get('/p/pati')
            ->assertStatus(301)
            ->assertRedirect(url('/pati'));
    }

    /**
     * The catch-all is registered last precisely so this can never happen; a
     * real page must always beat a username.
     */
    #[DataProvider('realRoutes')]
    public function test_a_real_route_is_never_shadowed_by_the_catch_all(string $path): void
    {
        // Even with a provider whose slug is exactly that path, which the
        // reserved list prevents but the router must survive regardless.
        Provider::factory()->published()->create(['slug' => $path]);

        $this->get("/{$path}")->assertOk();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function realRoutes(): iterable
    {
        yield 'proveedores' => ['proveedores'];
        yield 'terminos' => ['terminos'];
        yield 'privacidad' => ['privacidad'];
    }

    public function test_reserved_names_cannot_be_registered(): void
    {
        $response = $this->post('/register', [
            'username' => 'reservar',
            'fullName' => 'Alguien',
            'phone' => '3055551234',
            'email' => 'x@example.com',
            'password' => 'SuperSecret123',
            'confirmPassword' => 'SuperSecret123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_the_slugs_already_in_production_are_not_reserved(): void
    {
        // pati and vane predate this list; reserving either would lock the two
        // live businesses out of their own address.
        $this->assertFalse(ReservedSlugs::contains('pati'));
        $this->assertFalse(ReservedSlugs::contains('vane'));
        $this->assertTrue(ReservedSlugs::contains('reservar'));
        $this->assertTrue(ReservedSlugs::contains('ADMIN'));
    }

    public function test_an_unknown_slug_still_404s(): void
    {
        $this->get('/no-existe-esta-persona')->assertNotFound();
    }
}
