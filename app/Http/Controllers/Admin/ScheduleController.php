<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTimeOffRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\Appointment;
use App\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function update(UpdateScheduleRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $data = $request->validated();

        foreach ($data['days'] as $day) {
            $provider->businessHours()->updateOrCreate(
                ['weekday' => $day['weekday']],
                [
                    'is_open' => $day['isOpen'],
                    'work_start_minute' => $day['workStart'],
                    'work_end_minute' => $day['workEnd'],
                ],
            );
        }

        $provider->update([
            'lunch_start_minute' => $data['lunchStart'],
            'lunch_end_minute' => $data['lunchEnd'],
            'buffer_minutes' => $data['bufferMinutes'],
            // The two columns on `providers` are no longer what availability
            // reads, but plenty still does — the fallback for a provider with
            // no rows, and the window a brand-new day inherits. Kept as the
            // outer bounds of the week so they never contradict it.
            ...$this->weekBounds($data['days']),
        ]);

        $conflicts = $this->countConflicts($provider->fresh());

        $redirect = to_route('admin.horario')->with('success', 'admin.scheduleUpdated');

        if ($conflicts > 0) {
            // Appointments are data, not derived from the schedule —
            // GenerateAvailableSlots only uses the schedule for *new*
            // candidates, existing appointments still block their slot and
            // can be confirmed/cancelled regardless. Nothing breaks; the
            // provider just deserves to know some bookings now fall outside
            // their stated hours, or on a day they just closed.
            $redirect->with('warning', ['key' => 'admin.scheduleConflictWarning', 'count' => $conflicts]);
        }

        return $redirect;
    }

    public function storeTimeOff(StoreTimeOffRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $data = $request->validated();

        $provider->timeOff()->create([
            'starts_on' => $data['startsOn'],
            'ends_on' => $data['endsOn'],
            'reason' => $data['reason'] ?? null,
        ]);

        $redirect = to_route('admin.horario')->with('success', 'admin.timeOffAdded');

        $conflicts = $this->countConflicts($provider->fresh());

        if ($conflicts > 0) {
            $redirect->with('warning', ['key' => 'admin.scheduleConflictWarning', 'count' => $conflicts]);
        }

        return $redirect;
    }

    /**
     * The panic button: something happened and no more clients can be taken
     * today. Blocks the next day or two in one tap.
     *
     * Dates are resolved from the provider's own clock, never from the
     * browser's — someone on holiday in another timezone tapping "close today"
     * must close *their salon's* today.
     *
     * This stops new bookings; it deliberately does not cancel the ones
     * already made. Those clients are expecting to be seen, and telling them
     * is a conversation, not a database write.
     */
    public function pause(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $days = max(1, min($request->integer('days', 1), 7));
        $from = $provider->currentTime()->startOfDay();
        $to = $from->addDays($days - 1);

        $provider->timeOff()->create([
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'reason' => __('admin.timeOffPauseReason'),
        ]);

        $alreadyBooked = $provider->appointments()->blocking()
            ->whereBetween('starts_at', [$from->utc(), $to->endOfDay()->utc()])
            ->count();

        $redirect = to_route('admin.horario')->with('success', 'admin.agendaPaused');

        if ($alreadyBooked > 0) {
            $redirect->with('warning', ['key' => 'admin.agendaPausedBooked', 'count' => $alreadyBooked]);
        }

        return $redirect;
    }

    /**
     * Scoped through the provider's own relation rather than a route-model
     * binding: a block belonging to someone else must be indistinguishable
     * from one that never existed, so neither can be probed for.
     */
    public function destroyTimeOff(Request $request, int $timeOff): RedirectResponse
    {
        $request->user()->provider->timeOff()->whereKey($timeOff)->firstOrFail()->delete();

        return to_route('admin.horario')->with('success', 'admin.timeOffRemoved');
    }

    /**
     * Earliest start and latest end across the days that are actually open.
     * An all-closed week keeps the provider's current window: zeroing it would
     * write a nonsensical row that providers_work_window_chk rejects anyway.
     *
     * @param  list<array{weekday: int, isOpen: bool, workStart: int, workEnd: int}>  $days
     * @return array<string, int>
     */
    private function weekBounds(array $days): array
    {
        $open = array_filter($days, fn (array $day) => (bool) $day['isOpen']);

        if ($open === []) {
            return [];
        }

        return [
            'work_start_minute' => min(array_column($open, 'workStart')),
            'work_end_minute' => max(array_column($open, 'workEnd')),
        ];
    }

    /**
     * Counts future blocking appointments that no longer fit the schedule:
     * on a day now closed, outside that weekday's window, or inside the lunch
     * block. Mirrors GenerateAvailableSlots::busyIntervals()'s local-minute
     * conversion exactly.
     */
    private function countConflicts(Provider $provider): int
    {
        $provider->loadMissing(['businessHours', 'timeOff']);

        return $provider->appointments()
            ->blocking()
            ->where('starts_at', '>=', now()->utc())
            ->get(['starts_at', 'ends_at'])
            ->filter(function (Appointment $appointment) use ($provider) {
                $start = $appointment->starts_at->setTimezone($provider->timezone);
                $end = $appointment->ends_at->setTimezone($provider->timezone);

                $window = $provider->workingWindowOn(CarbonImmutable::parse($start)->startOfDay());

                if ($window === null) {
                    return true;
                }

                [$workStart, $workEnd] = $window;

                $startMinute = $start->hour * 60 + $start->minute;
                $endMinute = $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440;

                return $startMinute < $workStart
                    || $endMinute > $workEnd
                    || ($provider->lunch_start_minute < $provider->lunch_end_minute
                        && $startMinute < $provider->lunch_end_minute
                        && $endMinute > $provider->lunch_start_minute);
            })
            ->count();
    }
}
