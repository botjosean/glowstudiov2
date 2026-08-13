<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;

/**
 * Reached only when the broker rejects the token/email pair (expired, reused,
 * or tampered with) — a bad new password never gets here, that fails its own
 * field validation first (see PasswordValidationRules::passwordRules, still
 * 'confirmed' against password_confirmation). Flashed as a page-level error
 * instead of Fortify's default inline 'email' field error: the email field
 * doesn't even show on ResetPassword.vue, so an inline error there would
 * never render.
 */
class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                'email' => ['resetPassword.invalidToken'],
            ]);
        }

        return back()->with('error', 'resetPassword.invalidToken');
    }
}
