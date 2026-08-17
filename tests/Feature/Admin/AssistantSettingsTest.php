<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Support\Assistant\Receptionist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Configuración → Asistente de WhatsApp: what it calls her and what it says,
 * editable without a deploy.
 */
class AssistantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_defaults_she_would_get_if_she_wrote_nothing(): void
    {
        $provider = Provider::factory()->published()->receptionist()->create([
            'public_name' => 'Patricia Moreno',
        ]);

        $this->actingAs($provider->user)->get('/admin/asistente')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Asistente')
            ->where('settings.mode', Provider::BOT_RECEPTIONIST)
            ->where('settings.displayName', null)
            // Sent rather than duplicated in the Vue file, so the preview shows
            // the text the server would really send.
            ->where('defaults.displayName', 'Patricia Moreno')
            ->where('defaults.greeting', Receptionist::defaultGreeting())
            ->where('connection.connected', false)
        );
    }

    public function test_it_says_when_there_is_a_number_behind_all_this(): void
    {
        $provider = Provider::factory()->published()->create([
            'whatsapp_phone_number_id' => '868324373028256',
        ]);

        $this->actingAs($provider->user)->get('/admin/asistente')->assertInertia(fn (Assert $page) => $page
            ->where('connection.connected', true)
        );
    }

    public function test_she_can_change_what_the_assistant_calls_her(): void
    {
        $provider = Provider::factory()->published()->create(['public_name' => 'Patricia Moreno']);

        $this->actingAs($provider->user)->put('/admin/asistente', [
            'mode' => Provider::BOT_RECEPTIONIST,
            'displayName' => 'Pati',
            'businessName' => 'Glow Studio',
            'greeting' => '',
            'greetingReturning' => '',
            'intake' => '',
            'offersBookingLink' => false,
            'notes' => '',
        ])->assertRedirect('/admin/asistente');

        $provider->refresh();

        $this->assertSame('Pati', $provider->bot_display_name);
        $this->assertSame(Provider::BOT_RECEPTIONIST, $provider->bot_mode);
        $this->assertFalse($provider->bot_offers_booking_link);
        $this->assertSame('Pati', $provider->botDisplayName());
    }

    /**
     * Empty means "use the default", and an empty string is not that: it would
     * print nothing where her name belongs.
     */
    public function test_clearing_a_box_goes_back_to_the_default_rather_than_to_an_empty_message(): void
    {
        $provider = Provider::factory()->published()->create([
            'public_name' => 'Patricia Moreno',
            'bot_display_name' => 'Pati',
            'bot_greeting' => 'Un texto viejo',
        ]);

        $this->actingAs($provider->user)->put('/admin/asistente', [
            'mode' => Provider::BOT_RECEPTIONIST,
            'displayName' => '   ',
            'businessName' => '',
            'greeting' => '',
            'greetingReturning' => '',
            'intake' => '',
            'offersBookingLink' => true,
            'notes' => '',
        ]);

        $provider->refresh();

        $this->assertNull($provider->bot_display_name);
        $this->assertNull($provider->bot_greeting);
        $this->assertSame('Patricia Moreno', $provider->botDisplayName());
        $this->assertSame(Provider::DEFAULT_BUSINESS_NAME, $provider->botBusinessName());
    }

    public function test_an_invented_mode_is_rejected(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->put('/admin/asistente', [
            'mode' => 'superinteligente',
            'offersBookingLink' => true,
        ])->assertSessionHasErrors('mode');

        $this->assertSame(Provider::BOT_AGENT, $provider->fresh()->bot_mode);
    }

    public function test_a_guest_cannot_read_or_change_it(): void
    {
        $this->get('/admin/asistente')->assertRedirect('/iniciar-sesion');
        $this->put('/admin/asistente', [])->assertRedirect('/iniciar-sesion');
    }

    /**
     * She edits her own provider and no other — the route takes no parameter,
     * so there is nothing to tamper with, and this pins that.
     */
    public function test_saving_never_touches_another_providers_assistant(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create(['bot_display_name' => 'Vane']);

        $this->actingAs($mine->user)->put('/admin/asistente', [
            'mode' => Provider::BOT_RECEPTIONIST,
            'displayName' => 'Pati',
            'offersBookingLink' => true,
        ]);

        $this->assertSame('Vane', $theirs->fresh()->bot_display_name);
        $this->assertSame(Provider::BOT_AGENT, $theirs->fresh()->bot_mode);
    }
}
