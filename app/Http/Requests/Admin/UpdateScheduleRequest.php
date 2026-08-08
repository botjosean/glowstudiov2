<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    /**
     * Authorization lives in the route — this endpoint takes no {id}, the
     * target is always $request->user()->provider, so there is no
     * cross-tenant vector to guard against by construction.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors providers_lunch_window_chk, providers_buffer_chk and
     * provider_business_hours_window_chk exactly.
     *
     * All seven days are always sent, closed ones included: a partial payload
     * would leave the missing weekdays at whatever they were, and "I forgot to
     * send Sunday" and "Sunday is unchanged" are indistinguishable server-side.
     * A closed day still carries hours so reopening it restores what it had.
     *
     * Deliberately no rule forcing lunch inside the work window — the database
     * doesn't require it either, and GenerateAvailableSlots degrades
     * gracefully (the lunch filter simply never matches).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.isOpen' => ['required', 'boolean'],
            'days.*.workStart' => ['required', 'integer', 'between:0,1440', 'multiple_of:15'],
            'days.*.workEnd' => ['required', 'integer', 'between:0,1440', 'multiple_of:15', 'gt:days.*.workStart'],
            'lunchStart' => ['required', 'integer', 'between:0,1440', 'multiple_of:15'],
            'lunchEnd' => ['required', 'integer', 'between:0,1440', 'multiple_of:15', 'gte:lunchStart'],
            'bufferMinutes' => ['required', 'integer', 'between:0,300', 'multiple_of:15'],
        ];
    }
}
