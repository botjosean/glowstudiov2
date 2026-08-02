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
     * Mirrors providers_work_window_chk, providers_lunch_window_chk,
     * providers_buffer_chk and providers_quarter_hour_chk exactly.
     *
     * Deliberately no rule forcing lunch inside the work window — the
     * database doesn't require it either, and GenerateAvailableSlots
     * degrades gracefully (the lunch filter simply never matches).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workStart' => ['required', 'integer', 'between:0,1440', 'multiple_of:15'],
            'workEnd' => ['required', 'integer', 'between:0,1440', 'multiple_of:15', 'gt:workStart'],
            'lunchStart' => ['required', 'integer', 'between:0,1440', 'multiple_of:15'],
            'lunchEnd' => ['required', 'integer', 'between:0,1440', 'multiple_of:15', 'gte:lunchStart'],
            'bufferMinutes' => ['required', 'integer', 'between:0,300', 'multiple_of:15'],
        ];
    }
}
