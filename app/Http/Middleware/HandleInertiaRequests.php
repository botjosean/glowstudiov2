<?php

namespace App\Http\Middleware;

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
            ],
        ];
    }
}
