<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Fortify's default always lands on config('fortify.home') (/admin/citas).
 * A provider who hasn't finished the onboarding checklist lands on the
 * guided Admin/Inicio.vue instead — every login, not just the first, same
 * as the banner that keeps reappearing until every step is done. Once
 * onboardingComplete() flips true, this stops applying and login goes back
 * to the agenda like normal. GoogleAuthController's callback also resolves
 * this response for an existing-password Google login, so both paths agree.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $provider = $request->user()->provider;
        $target = $provider !== null && ! $provider->onboardingComplete()
            ? '/admin/inicio'
            : Fortify::redirects('login');

        return redirect()->intended($target);
    }
}
