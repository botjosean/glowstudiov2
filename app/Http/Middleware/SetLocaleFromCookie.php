<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromCookie
{
    private const SUPPORTED_LOCALES = ['es', 'en'];

    /**
     * The UI language lives client-side only (localStorage), so the server
     * has no idea what language the user is browsing in — validation
     * messages (422s) would always render in APP_LOCALE regardless of the
     * UI. setLocale() in resources/js/i18n/index.js mirrors the choice into
     * this cookie so server-rendered strings (validation errors, "Skin
     * Fade" style booking.slot_unavailable) match the UI language too.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            App::setLocale($locale);

            return $next($request);
        }

        // No cookie yet — first visit, or someone who never touched the
        // language switch. Fall back to the device's own language instead of
        // APP_LOCALE, which is why a Spanish-speaking client landing on a
        // profile saw the location card announce "In-studio service".
        // An explicit choice still wins: this only runs when there is none.
        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);

        if ($preferred !== null) {
            App::setLocale($preferred);
        }

        return $next($request);
    }
}
