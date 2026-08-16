<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_is_created_with_the_phone_normalized(): void
    {
        $provider = Provider::factory()->published()->create();

        $response = $this->actingAs($provider->user)->post('/admin/clientes', [
            'clientName' => '  Ana Sosa  ',
            'clientPhone' => '(305) 555-0199',
            'notes' => 'Alergia al amoniaco',
        ]);

        $client = $provider->clients()->sole();
        $response->assertRedirect("/admin/clientes/{$client->id}");
        $this->assertSame('Ana Sosa', $client->name);
        $this->assertSame('3055550199', $client->phone);
        $this->assertSame('Alergia al amoniaco', $client->notes);
    }

    public function test_a_duplicate_phone_is_rejected_per_provider(): void
    {
        $provider = Provider::factory()->published()->create();
        Client::factory()->for($provider)->create(['phone' => '3055550199']);

        $this->actingAs($provider->user)->post('/admin/clientes', [
            'clientName' => 'Otra',
            'clientPhone' => '3055550199',
        ])->assertSessionHasErrors('clientPhone');

        // A different provider may hold the same phone.
        $other = Provider::factory()->published()->create();
        $this->actingAs($other->user)->post('/admin/clientes', [
            'clientName' => 'Suya',
            'clientPhone' => '3055550199',
        ])->assertSessionHasNoErrors();
    }

    public function test_short_phones_are_rejected_and_empty_becomes_null(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/clientes', [
            'clientName' => 'Ana',
            'clientPhone' => '12345',
        ])->assertSessionHasErrors('clientPhone');

        $this->actingAs($provider->user)->post('/admin/clientes', [
            'clientName' => 'Ana',
            'clientPhone' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($provider->clients()->sole()->phone);
    }

    public function test_two_phoneless_cards_may_coexist(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/clientes', ['clientName' => 'Ana']);
        $this->actingAs($provider->user)->post('/admin/clientes', ['clientName' => 'Bea'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $provider->clients()->count());
    }

    public function test_update_saves_notes_and_sanitized_tags(): void
    {
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create(['phone' => '3055550199']);

        $this->actingAs($provider->user)->put("/admin/clientes/{$client->id}", [
            'clientName' => $client->name,
            'clientPhone' => '3055550199',
            'notes' => 'Prefiere tardes',
            'tags' => ['#vip', ' #vip ', '', 'de confianza'],
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('Prefiere tardes', $client->notes);
        $this->assertSame(['#vip', 'de confianza'], $client->tags);
    }

    public function test_deleting_a_card_never_touches_appointments(): void
    {
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create(['phone' => '3055550199']);
        $appointment = Appointment::factory()->for($provider)->create(['client_phone' => '3055550199']);

        $this->actingAs($provider->user)->delete("/admin/clientes/{$client->id}")
            ->assertRedirect('/admin/clientes');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_foreign_cards_cannot_be_updated_or_deleted(): void
    {
        $provider = Provider::factory()->published()->create();
        $foreign = Client::factory()->create();

        $this->actingAs($provider->user)->put("/admin/clientes/{$foreign->id}", [
            'clientName' => 'Hackeada',
        ])->assertForbidden();

        $this->actingAs($provider->user)->delete("/admin/clientes/{$foreign->id}")->assertForbidden();
    }

    public function test_contact_import_creates_cards_and_skips_duplicates_and_unusable_phones(): void
    {
        $provider = Provider::factory()->published()->create();
        Client::factory()->for($provider)->create(['name' => 'Ya Estaba', 'phone' => '3055550199']);

        $this->actingAs($provider->user)->post('/admin/clientes/importar', [
            'contacts' => [
                ['name' => 'Ana Nueva', 'phone' => '+1 (305) 555-0111'],
                ['name' => 'Repetida', 'phone' => '305-555-0199'],
                ['name' => 'Sin Teléfono', 'phone' => ''],
                ['name' => 'Corto', 'phone' => '12345'],
                ['name' => 'Bea Nueva', 'phone' => '3055550122'],
            ],
        ])->assertRedirect('/admin/clientes');

        $this->assertSame(3, $provider->clients()->count());
        $this->assertSame('Ana Nueva', $provider->clients()->where('phone', '3055550111')->sole()->name);
        // La existente conserva su nombre: la importación nunca pisa fichas.
        $this->assertSame('Ya Estaba', $provider->clients()->where('phone', '3055550199')->sole()->name);
    }

    public function test_contact_import_dedupes_within_the_same_batch(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->post('/admin/clientes/importar', [
            'contacts' => [
                ['name' => 'Ana', 'phone' => '3055550111'],
                ['name' => 'Ana Otra Vez', 'phone' => '(305) 555-0111'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $provider->clients()->count());
    }

    public function test_a_booking_with_a_new_phone_grows_a_card_on_any_channel(): void
    {
        $provider = Provider::factory()->published()->create();

        Appointment::factory()->for($provider)->create([
            'client_name' => 'Nueva Clienta',
            'client_phone' => '3055550122',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(10, 40),
        ]);

        $card = $provider->clients()->sole();
        $this->assertSame('Nueva Clienta', $card->name);
        $this->assertSame('3055550122', $card->phone);

        // A second booking reuses the card and never overwrites the
        // professional's own spelling of the name.
        $card->update(['name' => 'Nombre Corregido']);
        Appointment::factory()->for($provider)->create([
            'client_name' => 'nueva clienta',
            'client_phone' => '3055550122',
            'starts_at' => now()->addDay()->setTime(12, 0),
            'ends_at' => now()->addDay()->setTime(12, 40),
        ]);

        $this->assertSame(1, $provider->clients()->count());
        $this->assertSame('Nombre Corregido', $provider->clients()->sole()->name);
    }
}
