<?php

namespace App\Actions\Fortify;

use App\Enums\BusinessCategory;
use App\Models\User;
use App\Support\Format;
use App\Support\ReservedSlugs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly CreatesProviderProfile $providerProfiles) {}

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

        // Same normalize-before-validate reasoning as username: the email is
        // saved lowercased, so unique must compare the lowercased value or
        // "Correo@x.com" sails past validation and dies on the unique index.
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        // Field names are camelCase because that's what SignUp.vue sends;
        // error bags land on errors.fullName / errors.confirmPassword etc.
        Validator::make($input, [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._]+$/',
                Rule::notIn(ReservedSlugs::all()), Rule::unique(User::class)],
            'fullName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits:10', Rule::unique(User::class)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => [
                // array_filter, not array_diff: passwordRules() contains a
                // Password rule object, and array_diff casts every element
                // to string for comparison, which throws on a non-stringable object.
                ...array_filter($this->passwordRules(), fn ($rule) => $rule !== 'confirmed'),
                'confirmed:confirmPassword',
            ],
            // Obligatorio desde el registro, no un paso más de la guía: sin
            // rubro, el Taller de Contenido no sabe si le está armando un post
            // a una barbería o a un salón de uñas, y sale con la paleta y los
            // hashtags del rubro equivocado. Pasó de verdad — una foto de
            // cabello salió en rosa con #nailart.
            'businessCategory' => ['required', Rule::enum(BusinessCategory::class)],
        ], [
            'businessCategory.required' => 'Elegí a qué te dedicás.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'phone.unique' => 'Ese número de teléfono ya tiene una cuenta. Inicia sesión o usa otro número.',
            'email.unique' => 'Ese correo ya tiene una cuenta. Inicia sesión o usa otro correo.',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['fullName'],
                'username' => $input['username'],
                'email' => Str::lower($input['email']),
                'phone' => $input['phone'],
                'password' => $input['password'],
            ]);

            $this->providerProfiles->create(
                $user,
                $input['fullName'],
                $input['username'],
                businessCategory: $input['businessCategory'],
            );

            return $user;
        });
    }
}
