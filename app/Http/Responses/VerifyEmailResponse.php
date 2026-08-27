<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Only VerifyEmailResponse checks onboarding, not a regular login (see
 * LoginResponse — reverted to Fortify's default): clicking the emailed link
 * is the tail end of creating an account, so landing an unfinished
 * checklist on Admin/Inicio.vue here is a one-time thing, not something
 * that keeps happening on every day she logs back in.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $provider = $request->user()->provider;
        $target = $provider !== null && ! $provider->onboardingComplete()
            ? '/admin/inicio'
            : Fortify::redirects('email-verification');

        return redirect()->intended($target);
    }
}
