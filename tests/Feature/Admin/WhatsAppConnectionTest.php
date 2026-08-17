<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Connecting a professional's own WhatsApp without anybody doing it by hand.
 *
 * Faked against Kapso on purpose: the real calls create customers and setup
 * links tied to live Meta accounts, so the shapes are pinned here rather than
 * discovered in production.
 */
class WhatsAppConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER = '8423c88a-b1e0-413b-9f40-b4dab5257c60';

    private const NUMBER = '868324373028256';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.api_key' => 'test-api-key',
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.kapso.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_it_sends_her_to_kapsos_hosted_page(): void
    {
        $this->fakeKapso();
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)
            ->post('/admin/asistente/conectar')
            ->assertRedirect('https://app.kapso.ai/whatsapp/setup/tok_123');
    }

    /**
     * Never a provisioned number: one of those cannot be used from a phone at
     * all, which would leave her reading clients in a browser while her hands
     * are busy. Coexistence keeps her number, her chats and her app.
     */
    public function test_the_setup_link_is_locked_to_her_own_number(): void
    {
        $this->fakeKapso();
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)->post('/admin/asistente/conectar');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/setup_links')) {
                return false;
            }

            $link = $request['setup_link'];

            return $link['provision_phone_number'] === false
                && $link['allowed_connection_types'] === ['coexistence']
                && $link['language'] === 'es';
        });
    }

    /**
     * A customer created by hand in Kapso's panel (which is how the first one
     * got there) must be adopted, not duplicated.
     */
    public function test_it_reuses_the_kapso_customer_that_already_exists(): void
    {
        $this->fakeKapso();
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)->post('/admin/asistente/conectar');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/platform/v1/customers'));
    }

    public function test_coming_back_saves_the_number_and_wires_the_webhook(): void
    {
        $this->fakeKapso();
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)
            ->get('/admin/asistente/conectado')
            ->assertRedirect('/admin/asistente');

        $this->assertSame(self::NUMBER, $provider->fresh()->whatsapp_phone_number_id);
        $this->assertSame('admin.botConnected', session('success'));

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/webhooks') || $request->method() !== 'POST') {
                return false;
            }

            $hook = $request['whatsapp_webhook'];

            return $hook['url'] === route('api.kapso.webhook')
                && $hook['secret_key'] === 'test-webhook-secret'
                && $hook['events'] === ['whatsapp.message.received']
                && $hook['buffer_enabled'] === true;
        });
    }

    /**
     * The redirect is a hint that she finished, never proof. Writing the
     * number off the URL would wire the assistant to a number Meta has not
     * confirmed.
     */
    public function test_a_number_that_is_not_connected_yet_is_not_claimed(): void
    {
        $this->fakeKapso(numberStatus: 'PENDING');
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)->get('/admin/asistente/conectado');

        $this->assertNull($provider->fresh()->whatsapp_phone_number_id);
        $this->assertSame('admin.botConnectPending', session('warning'));
    }

    /**
     * A connected number with no webhook is *silent*, and silence looks like a
     * quiet afternoon from the panel. She has to be told.
     */
    public function test_it_admits_it_when_the_webhook_could_not_be_attached(): void
    {
        $this->fakeKapso(webhookFails: true);
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)->get('/admin/asistente/conectado');

        // The number is still saved: it really is connected, and hiding that
        // would send her round the whole flow again for nothing.
        $this->assertSame(self::NUMBER, $provider->fresh()->whatsapp_phone_number_id);
        $this->assertSame('admin.botConnectedNoWebhook', session('success'));
    }

    /**
     * Every inbound message is routed by phone number id, so two providers
     * sharing one would answer each other's clients.
     */
    public function test_a_number_already_used_by_somebody_else_is_refused(): void
    {
        $this->fakeKapso();
        Provider::factory()->published()->create(['whatsapp_phone_number_id' => self::NUMBER]);
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)->get('/admin/asistente/conectado');

        $this->assertNull($provider->fresh()->whatsapp_phone_number_id);
        $this->assertSame('admin.botNumberTaken', session('error'));
    }

    public function test_an_already_connected_provider_is_not_sent_round_again(): void
    {
        $this->fakeKapso();
        $provider = Provider::factory()->published()->create(['whatsapp_phone_number_id' => self::NUMBER]);

        $this->actingAs($provider->user)
            ->post('/admin/asistente/conectar')
            ->assertRedirect('/admin/asistente');

        Http::assertNothingSent();
    }

    public function test_a_kapso_failure_does_not_leave_her_staring_at_an_error_page(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['error' => 'boom'], 500)]);
        $provider = Provider::factory()->published()->create(['slug' => 'pati']);

        $this->actingAs($provider->user)
            ->post('/admin/asistente/conectar')
            ->assertRedirect('/admin/asistente');

        $this->assertSame('admin.botConnectFailed', session('error'));
    }

    public function test_a_guest_cannot_start_a_connection(): void
    {
        $this->post('/admin/asistente/conectar')->assertRedirect('/iniciar-sesion');
        $this->get('/admin/asistente/conectado')->assertRedirect('/iniciar-sesion');
    }

    private function fakeKapso(string $numberStatus = 'CONNECTED', bool $webhookFails = false): void
    {
        Http::fake([
            'api.kapso.ai/platform/v1/customers?*' => Http::response(['data' => [
                ['id' => self::CUSTOMER, 'external_customer_id' => 'pati', 'name' => 'Pati'],
            ]]),
            'api.kapso.ai/platform/v1/customers/*/setup_links' => Http::response([
                'data' => ['id' => 'sl_1', 'url' => 'https://app.kapso.ai/whatsapp/setup/tok_123'],
            ], 201),
            'api.kapso.ai/platform/v1/whatsapp/phone_numbers?*' => Http::response(['data' => [
                [
                    'phone_number_id' => self::NUMBER,
                    'customer_id' => self::CUSTOMER,
                    'status' => $numberStatus,
                    'display_phone_number' => '+1 404-451-8022',
                ],
            ]]),
            'api.kapso.ai/platform/v1/whatsapp/phone_numbers/*/webhooks' => $webhookFails
                ? Http::response(['error' => 'nope'], 422)
                : Http::sequence()
                    // Not there yet, created, then confirmed present.
                    ->push(['data' => []])
                    ->push(['data' => ['id' => 'wh_1']], 201)
                    ->push(['data' => [['url' => route('api.kapso.webhook'), 'active' => true]]]),
        ]);
    }
}
