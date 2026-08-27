<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Clicking the emailed link is the moment a manually-signed-up provider
 * first actually gets in — same reasoning as LoginResponse, so it shares
 * the same onboarding check: unfinished checklist lands on Admin/Inicio.vue
 * instead of an empty agenda.
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
