<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;

/**
 * Fortify's default flashes an English 'status' session key that nothing
 * in this app reads. This flashes 'success' with a translation key instead,
 * so FlashMessage.vue picks it up the same way every other admin flash does.
 */
class EmailVerificationLinkSentResponse implements EmailVerificationNotificationSentResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 202)
            : back()->with('success', 'verifyEmail.linkSent');
    }
}
