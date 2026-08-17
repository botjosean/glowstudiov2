<?php

namespace Tests\Feature\Admin;

use App\Actions\Booking\GenerateAvailableSlots;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_pending_appointment(): void
    {
        $provider = Provider::factory()->published()->create();
        $appointment = Appointment::factory()->for($provider)->pending()->create();

        $response = $this->actingAs($provider->user)
            ->patch("/admin/citas/{$appointment->id}/confirmar");

        $response->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertSame('confirmed', $appointment->status->value);
        $this->assertNotNull($appointment->confirmed_at);
        $this->assertSame('admin.appointmentConfirmed', session('success'));
    }

    public function test_confirming_auto_notifies_when_whatsapp_is_connected(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

        $provider = Provider::factory()->published()->create(['whatsapp_phone_number_id' => '868324373028256']);
        $appointment = Appointment::factory()->for($provider)->pending()->create(['client_phone' => '3055550142']);

        $response = $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/confirmar");

        $response->assertSessionHasNoErrors();
        $this->assertTrue(session('notified'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.kapso.ai/meta/whatsapp/v24.0/868324373028256/messages'
            && $request['to'] === '13055550142'
            && str_contains($request['text']['body'], $appointment->client_name));
    }

    public function test_confirming_does_not_call_kapso_when_whatsapp_is_not_connected(): void
    {
        Http::fake();

        $provider = Provider::factory()->published()->create(['whatsapp_phone_number_id' => null]);
        $appointment = Appointment::factory()->for($provider)->pending()->create();

        $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/confirmar");

        $this->assertFalse(session('notified'));
        Http::assertNothingSent();
    }

    public function test_confirming_still_succeeds_when_kapso_rejects_the_send(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $provider = Provider::factory()->published()->create(['whatsapp_phone_number_id' => '868324373028256']);
        $appointment = Appointment::factory()->for($provider)->pending()->create();

        $response = $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/confirmar");

        $response->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $appointment->fresh()->status->value);
        $this->assertFalse(session('notified'));
    }

    public function test_rejecting_a_pending_appointment_uses_the_rejected_message(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

        $provider = Provider::factory()->published()->create(['whatsapp_phone_number_id' => '868324373028256']);
        $appointment = Appointment::factory()->for($provider)->pending()->create();

        $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/cancelar");

        $this->assertTrue(session('notified'));
        Http::assertSent(fn ($request) => str_contains($request['text']['body'], "can't take your"));
    }

    public function test_cancelling_a_confirmed_appointment_uses_the_cancelled_message(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

        $provider = Provider::factory()->published()->create(['whatsapp_phone_number_id' => '868324373028256']);
        $appointment = Appointment::factory()->for($provider)->confirmed()->create();

        $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/cancelar");

        Http::assertSent(fn ($request) => str_contains($request['text']['body'], 'had to cancel'));
    }

    public function test_rejecting_a_pending_appointment(): void
    {
        $provider = Provider::factory()->published()->create();
        $appointment = Appointment::factory()->for($provider)->pending()->create();

        $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/cancelar");

        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status->value);
        $this->assertNotNull($appointment->cancelled_at);
        $this->assertSame('admin.appointmentCancelled', session('success'));
    }

    public function test_cancelling_a_confirmed_appointment(): void
    {
        $provider = Provider::factory()->published()->create();
        $appointment = Appointment::factory()->for($provider)->confirmed()->create();

        $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/cancelar");

        $this->assertSame('cancelled', $appointment->fresh()->status->value);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function illegalTransitions(): iterable
    {
        yield 'confirm a cancelled appointment' => ['cancelled', 'confirmar'];
        yield 'confirm a closed appointment' => ['closed', 'confirmar'];
        yield 'cancel a cancelled appointment' => ['cancelled', 'cancelar'];
        yield 'cancel a closed appointment' => ['closed', 'cancelar'];
    }

    #[DataProvider('illegalTransitions')]
    public function test_illegal_transitions_are_rejected(string $initialStatus, string $action): void
    {
        $provider = Provider::factory()->published()->create();
        $appointment = Appointment::factory()->for($provider)->create(['status' => $initialStatus]);

        $response = $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/{$action}");

        $response->assertSessionHasErrors('status');
        $this->assertSame($initialStatus, $appointment->fresh()->status->value);
    }

    public function test_a_provider_cannot_confirm_another_providers_appointment(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirAppointment = Appointment::factory()->for($theirs)->pending()->create();

        $this->actingAs($mine->user)
            ->patch("/admin/citas/{$theirAppointment->id}/confirmar")
            ->assertForbidden();

        $this->assertSame('pending', $theirAppointment->fresh()->status->value);
    }

    public function test_a_provider_cannot_cancel_another_providers_appointment(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirAppointment = Appointment::factory()->for($theirs)->pending()->create();

        $this->actingAs($mine->user)
            ->patch("/admin/citas/{$theirAppointment->id}/cancelar")
            ->assertForbidden();

        $this->assertSame('pending', $theirAppointment->fresh()->status->value);
    }

    public function test_cancelling_frees_the_slot_for_new_bookings(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->withSchedule(540, 1200, 0, 0, 0)->published()->create();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);
        $day = $provider->currentTime()->addDay();

        $appointment = Appointment::factory()->for($provider)->forService($service)
            ->at($day->setTime(10, 0))->confirmed()->create();

        $slotsBefore = app(GenerateAvailableSlots::class)->handle($provider, $service, $day->startOfDay());
        $this->assertNotContains(600, $slotsBefore); // 10:00 taken

        $this->actingAs($provider->user)->patch("/admin/citas/{$appointment->id}/cancelar");

        $slotsAfter = app(GenerateAvailableSlots::class)->handle($provider, $service, $day->startOfDay());
        $this->assertContains(600, $slotsAfter); // 10:00 freed
    }
}
