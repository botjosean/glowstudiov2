<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeOffRequest extends FormRequest
{
    /**
     * No {id} in the route — the block is always attached to
     * $request->user()->provider, so there is nothing cross-tenant to guard.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors provider_time_off_range_chk. A single day off is the same date
     * on both ends, so `after_or_equal` rather than `after`.
     *
     * No upper bound on how far ahead a block may start: someone planning next
     * year's holidays is doing the right thing, and the booking horizon
     * already limits what a client can reach.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'reason' => ['nullable', 'string', 'max:80'],
        ];
    }
}
