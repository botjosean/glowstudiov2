<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\Appointment;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;

class ScheduleController extends Controller
{
    public function update(UpdateScheduleRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $data = $request->validated();

        $provider->update([
            'work_start_minute' => $data['workStart'],
            'work_end_minute' => $data['workEnd'],
            'lunch_start_minute' => $data['lunchStart'],
            'lunch_end_minute' => $data['lunchEnd'],
            'buffer_minutes' => $data['bufferMinutes'],
        ]);

        $conflicts = $this->countConflicts($provider);

        $redirect = to_route('admin.horario')->with('success', 'admin.scheduleUpdated');

        if ($conflicts > 0) {
            // Appointments are data, not derived from the schedule —
            // GenerateAvailableSlots only uses work/lunch hours for *new*
            // candidates, existing appointments still block their slot and
            // can be confirmed/cancelled regardless. Nothing breaks; the
            // provider just deserves to know some bookings are now outside
            // their stated hours.
            $redirect->with('warning', ['key' => 'admin.scheduleConflictWarning', 'count' => $conflicts]);
        }

        return $redirect;
    }

    /**
     * Counts future blocking appointments that fall outside the
     * (already-saved) new work window or inside the new lunch block.
     * Mirrors GenerateAvailableSlots::busyIntervals()'s local-minute
     * conversion exactly.
     */
    private function countConflicts(Provider $provider): int
    {
        return $provider->appointments()
            ->blocking()
            ->where('starts_at', '>=', now()->utc())
            ->get(['starts_at', 'ends_at'])
            ->filter(function (Appointment $appointment) use ($provider) {
                $start = $appointment->starts_at->setTimezone($provider->timezone);
                $end = $appointment->ends_at->setTimezone($provider->timezone);

                $startMinute = $start->hour * 60 + $start->minute;
                $endMinute = $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440;

                return $startMinute < $provider->work_start_minute
                    || $endMinute > $provider->work_end_minute
                    || ($provider->lunch_start_minute < $provider->lunch_end_minute
                        && $startMinute < $provider->lunch_end_minute
                        && $endMinute > $provider->lunch_start_minute);
            })
            ->count();
    }
}
