<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InicioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_provider_sees_every_step_pending(): void
    {
        $provider = Provider::factory()->create([
            'bio' => '',
            'address_line' => null,
        ]);

        $this->actingAs($provider->user)->get('/admin/inicio')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inicio')
            ->where('checklist.profileComplete', false)
            ->where('checklist.hasActiveServices', false)
            ->where('checklist.whatsappConnected', false)
            ->where('checklist.published', false)
            ->where('checklist.hasAppointments', false)
            ->where('summary.todayCount', 0)
            ->where('summary.pendingCount', 0)
        );
    }

    public function test_a_fully_set_up_provider_sees_every_step_done(): void
    {
        $provider = Provider::factory()->published()->create([
            'address_line' => '3370 Sugarloaf Pkwy',
            'whatsapp_phone_number_id' => '868324373028256',
        ]);
        Service::factory()->for($provider)->create();
        Appointment::factory()->for($provider)->create();

        $this->actingAs($provider->user)->get('/admin/inicio')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inicio')
            ->where('checklist.profileComplete', true)
            ->where('checklist.hasActiveServices', true)
            ->where('checklist.whatsappConnected', true)
            ->where('checklist.published', true)
            ->where('checklist.hasAppointments', true)
            ->where('publicUrl', route('providers.show', $provider))
        );
    }

    public function test_a_mobile_provider_completes_the_profile_with_a_service_area(): void
    {
        $provider = Provider::factory()->mobile()->create([
            'service_area' => 'Atlanta y alrededores',
            'address_line' => null,
        ]);

        $this->actingAs($provider->user)->get('/admin/inicio')->assertInertia(fn (Assert $page) => $page
            ->where('checklist.profileComplete', true)
        );
    }

    public function test_only_active_services_count_for_the_checklist(): void
    {
        $provider = Provider::factory()->create();
        Service::factory()->for($provider)->inactive()->create();

        $this->actingAs($provider->user)->get('/admin/inicio')->assertInertia(fn (Assert $page) => $page
            ->where('checklist.hasActiveServices', false)
        );
    }

    public function test_the_summary_counts_todays_blocking_and_pending_appointments(): void
    {
        // Noon UTC = morning in America/New_York, so "today" is the same
        // calendar day in both zones and the fixture times stay unambiguous.
        $this->travelTo(CarbonImmutable::parse('2026-08-05 16:00:00', 'UTC'));

        $provider = Provider::factory()->create();

        // Today, pending: counts for both numbers.
        Appointment::factory()->for($provider)->create([
            'starts_at' => CarbonImmutable::parse('2026-08-05 18:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-05 18:40:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);
        // Today, cancelled: counts for neither.
        Appointment::factory()->for($provider)->create([
            'starts_at' => CarbonImmutable::parse('2026-08-05 19:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-05 19:40:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled->value,
        ]);
        // Tomorrow, pending: only the pending number.
        Appointment::factory()->for($provider)->create([
            'starts_at' => CarbonImmutable::parse('2026-08-06 18:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-06 18:40:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->actingAs($provider->user)->get('/admin/inicio')->assertInertia(fn (Assert $page) => $page
            ->where('summary.todayCount', 1)
            ->where('summary.pendingCount', 2)
        );
    }
}
