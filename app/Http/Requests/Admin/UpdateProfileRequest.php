<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Format;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Authorization lives in the route — this endpoint takes no {id}, the
     * target is always $request->user(), so there is no cross-tenant
     * vector to guard against by construction.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizing username to lowercase here (not just relying on the
     * User model's set: mutator) closes a real bug: Rule::unique compares
     * the *submitted* value, and Postgres string comparison is
     * case-sensitive. Submitting "PatiB" while "patib" exists would pass
     * unique, then the mutator lowercases on save -> unique index
     * violation -> 500. Normalizing before validation closes that.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Format::digitsOnly((string) $this->input('phone', '')),
            'username' => Str::lower(trim((string) $this->input('username', ''))),
        ]);
    }

    /**
     * email is deliberately absent — it's the login credential and this
     * request neither accepts nor writes it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._]+$/',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'publicName' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'digits:10'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
