<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Provider;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SaleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_lists_only_the_providers_own_sales(): void
    {
        $provider = Provider::factory()->published()->create();
        $mine = Sale::factory()->for($provider)->create(['amount' => 40, 'tip' => 5]);
        Sale::factory()->create();

        $this->actingAs($provider->user)->get('/admin/ventas')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Ventas')
            ->has('sales', 1)
            ->where('sales.0.id', $mine->id)
            ->where('sales.0.amount', 40)
            ->where('sales.0.tip', 5)
            ->has('acceptedMethods')
            ->has('allMethods', count(Sale::PAYMENT_METHODS))
        );
    }

    public function test_a_sale_snapshots_the_client_name(): void
    {
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create(['name' => 'Ana Sosa']);

        $this->actingAs($provider->user)->post('/admin/ventas', [
            'clientId' => $client->id,
            'amount' => '47.509',
            'tip' => 5,
            'paymentMethod' => 'cash',
        ])->assertRedirect('/admin/ventas');

        $sale = $provider->sales()->sole();
        $this->assertSame('Ana Sosa', $sale->client_name);
        $this->assertSame($client->id, $sale->client_id);
        $this->assertSame('47.51', $sale->amount);
        $this->assertSame('5.00', $sale->tip);

        // Deleting the card keeps the ledger intact, link gone.
        $client->delete();
        $sale->refresh();
        $this->assertNull($sale->client_id);
        $this->assertSame('Ana Sosa', $sale->client_name);
    }

    public function test_a_foreign_client_id_records_as_nobody(): void
    {
        $provider = Provider::factory()->published()->create();
        $foreign = Client::factory()->create(['name' => 'Ajena']);

        $this->actingAs($provider->user)->post('/admin/ventas', [
            'clientId' => $foreign->id,
            'amount' => 20,
            'paymentMethod' => 'cash',
        ])->assertSessionHasNoErrors();

        $sale = $provider->sales()->sole();
        $this->assertNull($sale->client_id);
        $this->assertNull($sale->client_name);
    }

    public function test_only_accepted_methods_are_allowed(): void
    {
        $provider = Provider::factory()->published()->create();
        $provider->update(['payment_methods' => ['cash', 'zelle']]);

        $this->actingAs($provider->user)->post('/admin/ventas', [
            'amount' => 20,
            'paymentMethod' => 'paypal',
        ])->assertSessionHasErrors('paymentMethod');

        $this->actingAs($provider->user)->post('/admin/ventas', [
            'amount' => 20,
            'paymentMethod' => 'zelle',
        ])->assertSessionHasNoErrors();
    }

    public function test_accepted_methods_can_be_updated_but_never_emptied(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->put('/admin/ventas/metodos', [
            'methods' => ['cash', 'venmo', 'venmo'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['cash', 'venmo'], $provider->fresh()->payment_methods);

        $this->actingAs($provider->user)->put('/admin/ventas/metodos', [
            'methods' => [],
        ])->assertSessionHasErrors('methods');

        $this->actingAs($provider->user)->put('/admin/ventas/metodos', [
            'methods' => ['bitcoin'],
        ])->assertSessionHasErrors('methods.0');
    }

    public function test_deleting_a_sale_is_scoped_to_the_owner(): void
    {
        $provider = Provider::factory()->published()->create();
        $mine = Sale::factory()->for($provider)->create();
        $foreign = Sale::factory()->create();

        $this->actingAs($provider->user)->delete("/admin/ventas/{$foreign->id}")->assertForbidden();

        $this->actingAs($provider->user)->delete("/admin/ventas/{$mine->id}")
            ->assertRedirect('/admin/ventas');
        $this->assertDatabaseMissing('sales', ['id' => $mine->id]);
    }

    public function test_amount_is_required_and_positive(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/ventas', [
            'amount' => 0,
            'paymentMethod' => 'cash',
        ])->assertSessionHasErrors('amount');
    }
}
