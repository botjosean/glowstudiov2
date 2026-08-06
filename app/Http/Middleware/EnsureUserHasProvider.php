<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasProvider
{
    /**
     * Rejects an authenticated user with no provider profile (e.g. the
     * seeded "Test User" fixture) from the admin panel, and eager-loads the
     * relation so admin controllers never lazy-load it — preventLazyLoading
     * is enabled, so a lazy load there would throw instead of query.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->user()->loadMissing('provider');

        if ($request->user()->provider === null) {
            // The general-admin account has no provider on purpose — its
            // home is the superadmin panel, not a 403.
            if (in_array($request->user()->username, config('app.superadmins'), true)) {
                return redirect()->route('superadmin.index');
            }

            abort(403);
        }

        return $next($request);
    }
}
