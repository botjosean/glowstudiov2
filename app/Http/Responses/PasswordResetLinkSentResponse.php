<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Bound as BOTH the success and failure response for a reset-link request.
 * Fortify's own failure response reveals "we don't have that email on file"
 * on the email field — an account-enumeration leak. cancelar_cita already
 * refuses to make that distinction for appointment ids on the bot side; this
 * is the same call for the panel's login. Also sidesteps needing Spanish
 * copies of Laravel's built-in passwords.* language lines (that file was
 * never added — see the isMobile/lang gap noted in CLAUDE.md).
 */
class PasswordResetLinkSentResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('success', 'forgotPassword.linkSent');
    }
}
