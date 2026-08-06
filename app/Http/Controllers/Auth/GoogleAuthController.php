<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateGoogleUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Continuar con Google" — the fast alternative to the manual sign-up form.
 *
 * Scope stops at getting a session and, if needed, a password: it hands off
 * to whatever a normal login already redirects to (Fortify's LoginResponse,
 * today /admin/citas) or to the one-time password step. What happens after
 * that (adding services, a schedule) is the existing, untouched onboarding.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, FindOrCreateGoogleUser $findOrCreateGoogleUser): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('sign-in')->with('error', 'auth.googleFailed');
        }

        if (! $googleUser->getEmail()) {
            return redirect()->route('sign-in')->with('error', 'auth.googleNoEmail');
        }

        $user = $findOrCreateGoogleUser->handle($googleUser);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->password === null) {
            return redirect()->route('password.create');
        }

        return app(LoginResponse::class)->toResponse($request);
    }
}
