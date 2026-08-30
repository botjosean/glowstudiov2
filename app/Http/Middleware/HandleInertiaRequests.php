<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Inertia's share() runs before route middleware (including
        // EnsureUserHasProvider), so the relation can't rely on anything
        // downstream having loaded it — load it explicitly here instead.
        // This is one extra indexed query per authenticated request, never
        // for guests, and it's an explicit load so preventLazyLoading
        // (enabled outside production) doesn't treat it as an N+1.
        $user?->loadMissing('provider');

        return [
            ...parent::share($request),
            // Explicit whitelist, never the raw model — spreading it would
            // leak password/remember_token the moment someone edits #[Hidden].
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'provider' => $user->provider ? [
                        'slug' => $user->provider->slug,
                        'publicName' => $user->provider->public_name,
                        'avatarPhoto' => MediaUrl::resolve($user->provider->avatar_photo_url),
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'booking' => fn () => $request->session()->get('booking'),
                // La nota que el sistema le propone tras guardar referencias,
                // con los ids de la tanda. Ver ContentController::store().
                'noteSuggestion' => fn () => $request->session()->get('noteSuggestion'),
                // El color que el sistema leyó de la foto, para que ella lo
                // corrija con sus palabras. Ver ContentController::store().
                'colorSuggestion' => fn () => $request->session()->get('colorSuggestion'),
                // Whether AppointmentStatusController auto-sent the WhatsApp
                // notice via Kapso — the frontend skips its own manual prompt
                // only when this is true, never on a static "bot connected" flag.
                'notified' => fn () => $request->session()->get('notified'),
            ],
            // Drives the layout's "N steps left" banner. Null (banner hidden)
            // for guests, provider-less users, and fully set-up providers.
            // Closure so partial reloads that don't request it skip the queries.
            'onboarding' => fn () => $this->onboardingFor($user?->provider),
            // Aviso aparte del contador de arriba, y sin poder cerrarse: si no
            // ha guardado su semana no recibe NINGUNA cita, y enterarse de eso
            // no puede depender de que no haya cerrado un banner hace un mes.
            'scheduleUnsaved' => $user?->provider !== null && ! $user->provider->hasSavedSchedule(),
        ];
    }

    /**
     * @return array{pending: int}|null
     */
    private function onboardingFor(?Provider $provider): ?array
    {
        if ($provider === null) {
            return null;
        }

        $pending = count(array_filter($provider->onboardingChecklist(), fn (bool $done) => ! $done));

        return $pending > 0 ? ['pending' => $pending] : null;
    }
}
