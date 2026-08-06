<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an authenticated user with no password yet to set one first.
 *
 * A null password is a normal, expected state for someone who just arrived
 * via Google — not an anomaly like EnsureUserHasProvider guards against — so
 * this redirects to the next step instead of aborting, the same way
 * Fortify's own EnsureEmailIsVerified redirects to the verification notice
 * rather than blocking outright.
 */
class EnsureUserHasPassword
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->password === null) {
            return $request->expectsJson()
                ? abort(403, 'You must set a password before continuing.')
                : redirect()->route('password.create');
        }

        return $next($request);
    }
}
