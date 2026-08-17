<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\AlertWaitingLeads;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Provider;
use App\Notifications\LeadsWaiting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The waiting list the receptionist fills, and what the professional can do
 * with it.
 */
class LeadInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_agenda_lists_this_providers_waiting_requests_oldest_first(): void
    {
        $provider = Provider::factory()->published()->receptionist()->create();

        $newer = Lead::factory()->for($provider)->create([
            'first_contact_at' => CarbonImmutable::now()->subHour(),
            'name' => 'María',
        ]);
        $older = Lead::factory()->for($provider)->create([
            'first_contact_at' => CarbonImmutable::now()->subDay(),
            'name' => 'Ana',
        ]);

        $this->actingAs($provider->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->has('leads', 2)
            // Oldest first: this is a queue of people waiting, and the one who
            // has waited longest is the one about to give up.
            ->where('leads.0.id', $older->id)
            ->where('leads.1.id', $newer->id)
        );
    }

    public function test_another_providers_requests_are_never_listed(): void
    {
        $mine = Provider::factory()->published()->receptionist()->create();
        Lead::factory()->for(Provider::factory()->receptionist())->create();

        $this->actingAs($mine->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->has('leads', 0)
        );
    }

    /**
     * An agent books clients itself, so a waiting list would be a list of
     * nothing — and showing an empty section would only puzzle her.
     */
    public function test_an_agent_provider_gets_no_waiting_list(): void
    {
        $provider = Provider::factory()->published()->create();
        Lead::factory()->for($provider)->create();

        $this->actingAs($provider->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->has('leads', 0)
        );
    }

    public function test_handled_and_dismissed_requests_leave_the_list(): void
    {
        $provider = Provider::factory()->published()->receptionist()->create();
        $lead = Lead::factory()->for($provider)->create();

        $this->actingAs($provider->user)
            ->patch("/admin/solicitudes/{$lead->id}", ['status' => Lead::STATUS_HANDLED])
            ->assertRedirect('/admin/citas');

        $lead->refresh();
        $this->assertSame(Lead::STATUS_HANDLED, $lead->status);
        $this->assertNotNull($lead->answered_at);

        $this->actingAs($provider->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->has('leads', 0)
        );
    }

    public function test_a_request_belonging_to_somebody_else_is_forbidden(): void
    {
        $mine = Provider::factory()->published()->receptionist()->create();
        $theirs = Lead::factory()->for(Provider::factory()->receptionist())->create();

        $this->actingAs($mine->user)
            ->patch("/admin/solicitudes/{$theirs->id}", ['status' => Lead::STATUS_HANDLED])
            ->assertForbidden();

        $this->assertSame(Lead::STATUS_NEW, $theirs->fresh()->status);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $provider = Provider::factory()->published()->receptionist()->create();
        $lead = Lead::factory()->for($provider)->create();

        $this->actingAs($provider->user)
            ->patch("/admin/solicitudes/{$lead->id}", ['status' => 'inventado'])
            ->assertSessionHasErrors('status');
    }

    /**
     * Booking her is the ending the request was waiting for. Without this the
     * professional would book her and then have to remember to tick the
     * request off — bookkeeping nobody does twice.
     */
    public function test_booking_the_client_closes_her_request_by_itself(): void
    {
        $provider = Provider::factory()->published()->receptionist()->create();
        $lead = Lead::factory()->for($provider)->create(['phone' => '3055550142']);

        Appointment::factory()->for($provider)->pending()->create(['client_phone' => '3055550142']);

        $lead->refresh();
        $this->assertSame(Lead::STATUS_HANDLED, $lead->status);
        $this->assertNotNull($lead->answered_at);
    }

    public function test_booking_a_different_client_leaves_the_request_open(): void
    {
        $provider = Provider::factory()->published()->receptionist()->create();
        $lead = Lead::factory()->for($provider)->create(['phone' => '3055550142']);

        Appointment::factory()->for($provider)->pending()->create(['client_phone' => '3055559999']);

        $this->assertSame(Lead::STATUS_NEW, $lead->fresh()->status);
    }

    public function test_the_professional_is_told_about_requests_that_waited_too_long(): void
    {
        Notification::fake();
        config(['services.assistant.lead_alert_hours' => 3]);

        $provider = Provider::factory()->published()->receptionist()->create();

        $waiting = Lead::factory()->for($provider)->create([
            'first_contact_at' => CarbonImmutable::now()->subHours(5),
        ]);
        // Too recent to nag about.
        Lead::factory()->for($provider)->create([
            'first_contact_at' => CarbonImmutable::now()->subMinutes(20),
        ]);

        $this->artisan(AlertWaitingLeads::class)->assertSuccessful();

        Notification::assertSentTo($provider->user, LeadsWaiting::class);
        $this->assertNotNull($waiting->fresh()->alerted_at);
    }

    /**
     * An hourly job that re-reads the same rows would mail the same list every
     * hour until she opens the app, and a professional who learns to ignore
     * these has no alert at all.
     */
    public function test_it_never_nags_twice_about_the_same_request(): void
    {
        Notification::fake();
        config(['services.assistant.lead_alert_hours' => 3]);

        $provider = Provider::factory()->published()->receptionist()->create();
        Lead::factory()->for($provider)->create([
            'first_contact_at' => CarbonImmutable::now()->subHours(5),
        ]);

        $this->artisan(AlertWaitingLeads::class)->assertSuccessful();
        $this->artisan(AlertWaitingLeads::class)->assertSuccessful();

        Notification::assertSentToTimes($provider->user, LeadsWaiting::class, 1);
    }

    public function test_an_agent_provider_is_never_alerted(): void
    {
        Notification::fake();

        $provider = Provider::factory()->published()->create();
        Lead::factory()->for($provider)->create([
            'first_contact_at' => CarbonImmutable::now()->subDays(2),
        ]);

        $this->artisan(AlertWaitingLeads::class)->assertSuccessful();

        Notification::assertNothingSent();
    }
}
