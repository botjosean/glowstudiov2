<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

/**
 * Fortify's default redirects to route('login') only when config('fortify.views')
 * is true; here it's false, so the unmodified default would redirect(null)
 * instead. Overriding it to land back on /iniciar-sesion with a translated
 * flash matches how every other success message in this app is shown.
 */
class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : redirect()->route('sign-in')->with('success', 'resetPassword.success');
    }
}
