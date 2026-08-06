<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sets the password for an account that doesn't have one yet (a Google
 * sign-in that hasn't completed this step). Deliberately not the same code
 * path as changing an existing password: there is no current_password to
 * check here, because there is nothing to check it against.
 */
class SetInitialPassword
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => [
                ...array_filter($this->passwordRules(), fn ($rule) => $rule !== 'confirmed'),
                'confirmed:confirmPassword',
            ],
        ])->validate();

        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    }
}
