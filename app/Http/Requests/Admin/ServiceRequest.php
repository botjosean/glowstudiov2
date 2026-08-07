<?php

namespace App\Http\Requests\Admin;

use App\Enums\ServiceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /**
     * Authorization lives in the route (->can(...) in routes/web.php), not
     * here — a FormRequest resolves after the Authorize middleware, so
     * putting it here would let a 422 leak validation feedback about a
     * resource the caller doesn't own before the 403 ever fires.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shared by store and update — the rules are identical for both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // max:80 mirrors string('name', 80); a 81-char name would 500 without this.
            'name' => ['required', 'string', 'min:2', 'max:80'],
            // between + multiple_of mirrors services_duration_chk exactly.
            'durationMinutes' => ['required', 'integer', 'between:5,360', 'multiple_of:5'],
            'price' => ['required', 'integer', 'min:0', 'max:10000'],
            // Rule::enum mirrors services_category_chk.
            'category' => ['required', Rule::enum(ServiceCategory::class)],
        ];
    }
}
