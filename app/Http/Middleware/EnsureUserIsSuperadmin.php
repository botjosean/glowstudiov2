<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperadmin
{
    /**
     * 404, not 403: for anyone who isn't a configured superadmin the general
     * administration panel should not even appear to exist.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            in_array($request->user()?->username, config('app.superadmins'), true),
            404,
        );

        return $next($request);
    }
}
