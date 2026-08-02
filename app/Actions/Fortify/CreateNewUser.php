<?php

namespace App\Actions\Fortify;

use App\Models\Provider;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user, plus the provider
     * profile SignUp.vue implies ("Yo ofrezco servicios"). The profile is
     * created unpublished so an empty account doesn't appear on /proveedores.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // Normalize before validating: Rule::unique compares the submitted
        // value, and Postgres string comparison is case-sensitive. Without
        // this, submitting "PatiB" while "patib" exists passes unique, then
        // User's username mutator lowercases on save -> unique index
        // violation -> 500.
        $input['username'] = Str::lower(trim((string) ($input['username'] ?? '')));

        // SignUp.vue sends the phone pre-formatted for display ("(305)
        // 555-0142"), but digits:10 validates the raw value, so it must be
        // stripped before validating, not just before saving.
        $input['phone'] = Format::digitsOnly((string) ($input['phone'] ?? ''));

        // Field names are camelCase because that's what SignUp.vue sends;
        // error bags land on errors.fullName / errors.confirmPassword etc.
        Validator::make($input, [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._]+$/', Rule::unique(User::class)],
            'fullName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits:10'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => [
                // array_filter, not array_diff: passwordRules() contains a
                // Password rule object, and array_diff casts every element
                // to string for comparison, which throws on a non-stringable object.
                ...array_filter($this->passwordRules(), fn ($rule) => $rule !== 'confirmed'),
                'confirmed:confirmPassword',
            ],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['fullName'],
                'username' => $input['username'],
                'email' => Str::lower($input['email']),
                'phone' => $input['phone'],
                'password' => $input['password'],
            ]);

            $user->provider()->create([
                'slug' => $this->uniqueSlug($input['username']),
                'public_name' => $input['fullName'],
                'timezone' => 'America/New_York',
            ]);

            return $user;
        });
    }

    private function uniqueSlug(string $username): string
    {
        $base = Str::slug($username) ?: 'provider';
        $slug = $base;
        $suffix = 1;

        while (Provider::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
