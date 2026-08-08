<?php

namespace App\Actions\Booking;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes free appointment start times for a given provider/service/day.
 *
 * All arithmetic happens in integer minutes from local midnight; UTC only
 * enters at the query boundary (loading existing appointments) and is
 * converted back to local minutes immediately. A 15-minute candidate grid
 * is used rather than chaining slots by duration+buffer: chaining breaks
 * the moment two services on the same provider have different durations,
 * which they do here. Both strategies need the same overlap filter, so the
 * grid is strictly more available without being more complex.
 *
 * The working window comes from the provider's per-weekday schedule, and a
 * closed weekday or a dated time-off block yields no slots at all. Because
 * CreateAppointment revalidates through this same method inside its lock, a
 * closed day is enforced on creation too — not merely hidden in the picker.
 *
 * Not supported: overnight schedules (work_end < work_start).
 */
class GenerateAvailableSlots
{
    public const STEP_MINUTES = 15;

    public const HORIZON_DAYS = 90;

    /**
     * @param  Collection<int, Appointment>|null  $blockingAppointments  Pre-fetched blocking
     *                                                                   appointments for this exact local day, to avoid a query per day when called from forMonth().
     *                                                                   When null, queries the day's blocking appointments itself.
     * @return list<int> minutes-from-midnight, ascending
     */
    public function handle(
        Provider $provider,
        Service $service,
        CarbonImmutable $localDate,
        ?Collection $blockingAppointments = null,
    ): array {
        $duration = $service->duration_minutes;
        $buffer = $provider->buffer_minutes;
        $lunchStart = $provider->lunch_start_minute;
        $lunchEnd = $provider->lunch_end_minute;

        $localDate = $localDate->startOfDay();
        $today = $provider->currentTime()->startOfDay();

        // The cheap rejections run before any schedule lookup: forMonth() calls
        // this once per day, and resolving a window for a date already out of
        // the horizon would be work thrown away thirty times over.
        if ($localDate->lt($today) || $localDate->gt($today->addDays(self::HORIZON_DAYS))) {
            return [];
        }

        // Loaded once rather than per call — forMonth() would otherwise issue
        // two queries for each of ~30 days to answer a single month view.
        $provider->loadMissing(['businessHours', 'timeOff']);

        $window = $provider->workingWindowOn($localDate);

        // Closed weekday, or a day inside a holiday block.
        if ($window === null) {
            return [];
        }

        [$workStart, $workEnd] = $window;

        if ($workEnd <= $workStart || $workStart + $duration > $workEnd) {
            return [];
        }

        $busyIntervals = $this->busyIntervals($provider, $localDate, $blockingAppointments);

        $nowMinute = $localDate->isSameDay($today)
            ? ($provider->currentTime()->hour * 60 + $provider->currentTime()->minute)
            : null;

        $slots = [];

        for ($t = $workStart; $t + $duration <= $workEnd; $t += self::STEP_MINUTES) {
            if ($nowMinute !== null && $t < $nowMinute) {
                continue;
            }

            // Lunch is a rest block, not an appointment: buffer does not apply
            // around it, so ending exactly at lunchStart or starting exactly
            // at lunchEnd are both legal. lunchStart === lunchEnd disables it.
            if ($lunchStart < $lunchEnd && $t < $lunchEnd && $t + $duration > $lunchStart) {
                continue;
            }

            $overlapsExistingAppointment = false;

            foreach ($busyIntervals as [$busyStart, $busyEnd]) {
                // Buffer applies on both sides of an existing appointment.
                if (! ($t >= $busyEnd + $buffer || $t + $duration + $buffer <= $busyStart)) {
                    $overlapsExistingAppointment = true;
                    break;
                }
            }

            if (! $overlapsExistingAppointment) {
                $slots[] = $t;
            }
        }

        return $slots;
    }

    /**
     * @return array<string, int> 'Y-m-d' => free slot count, for every day in the month
     */
    public function forMonth(Provider $provider, Service $service, CarbonImmutable $monthStart): array
    {
        $monthStart = $monthStart->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        // One query for the whole month; sliced per day below — no N+1 across ~30 days.
        $appointments = $provider->appointments()
            ->blocking()
            ->overlapping($monthStart->utc(), $monthEnd->addDay()->utc())
            ->get(['starts_at', 'ends_at']);

        $availability = [];

        for ($date = $monthStart; $date->lte($monthEnd); $date = $date->addDay()) {
            $dayAppointments = $appointments->filter(
                fn ($appointment) => $appointment->starts_at->setTimezone($provider->timezone)->isSameDay($date)
                    || $appointment->ends_at->setTimezone($provider->timezone)->isSameDay($date)
            );

            $availability[$date->toDateString()] = count($this->handle($provider, $service, $date, $dayAppointments));
        }

        return $availability;
    }

    /**
     * @param  Collection<int, Appointment>|null  $blockingAppointments
     * @return list<array{0: int, 1: int}> [startMinute, endMinute] local, clamped to a single day
     */
    private function busyIntervals(Provider $provider, CarbonImmutable $localDate, ?Collection $blockingAppointments): array
    {
        $appointments = $blockingAppointments ?? $provider->appointments()
            ->blocking()
            ->overlapping($localDate->utc(), $localDate->addDay()->utc())
            ->get(['starts_at', 'ends_at']);

        return $appointments
            ->map(function ($appointment) use ($provider, $localDate) {
                $start = $appointment->starts_at->setTimezone($provider->timezone);
                $end = $appointment->ends_at->setTimezone($provider->timezone);

                $startMinute = $start->isSameDay($localDate) ? ($start->hour * 60 + $start->minute) : 0;
                $endMinute = $end->isSameDay($localDate) ? ($end->hour * 60 + $end->minute) : 1440;

                return [$startMinute, $endMinute];
            })
            ->all();
    }
}
