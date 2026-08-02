<?php

namespace App\Http\Requests;

use App\Actions\Booking\GenerateAvailableSlots;
use App\Models\Provider;
use App\Support\Format;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Public booking — no auth required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizes the phone to 10 digits so "(305) 555-0123",
     * "305-555-0123" and "+13055550123" all validate the same way.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Format::digitsOnly((string) $this->input('phone', '')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Deliberately does NOT check slot availability — that must happen
     * inside CreateAppointment's row lock, or this is exactly the TOCTOU
     * window a double-booking slips through.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Provider $provider */
        $provider = $this->route('provider');
        $today = $provider->currentTime()->toDateString();
        $horizon = $provider->currentTime()->addDays(GenerateAvailableSlots::HORIZON_DAYS)->toDateString();

        return [
            'date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$today}", "before_or_equal:{$horizon}"],
            'time' => ['required', 'date_format:H:i'],
            'fullName' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'digits:10'],
        ];
    }
}
