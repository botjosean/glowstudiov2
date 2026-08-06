<?php

namespace App\Http\Controllers;

use App\Actions\Booking\GenerateAvailableSlots;
use App\Models\Provider;
use App\Models\Service;
use App\Models\ServiceType;
use App\Support\Format;
use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    /**
     * Home shows the 4 lowest-position catalog types that have at least one
     * active service, with a distinct provider count and "from" price/duration.
     */
    private const HOME_SERVICE_LIMIT = 4;

    public function home(): Response
    {
        $types = ServiceType::active()
            ->withCount(['services as providers_count' => function ($query) {
                // Plain withCount emits count(*), which would double-count a
                // provider offering two services of the same type.
                $query->select(DB::raw('count(distinct services.provider_id)'))
                    ->where('services.is_active', true)
                    ->whereHas('provider', fn ($p) => $p->published());
            }])
            ->withMin(['services as min_price' => fn ($query) => $query->where('is_active', true)], 'price')
            ->withMin(['services as min_duration_minutes' => fn ($query) => $query->where('is_active', true)], 'duration_minutes')
            ->whereHas('services', fn ($query) => $query->where('is_active', true))
            ->orderBy('position')
            ->limit(self::HOME_SERVICE_LIMIT)
            ->get();

        return Inertia::render('Public/Home', [
            'stats' => [
                'services' => ServiceType::active()->count(),
                'providers' => Provider::published()->count(),
            ],
            'services' => $types->map(fn (ServiceType $type) => [
                'id' => $type->id,
                'icon' => $type->icon->value,
                'name' => $type->name,
                'duration' => Format::duration((int) $type->min_duration_minutes),
                'providersCount' => (int) $type->providers_count,
                'price' => (int) $type->min_price,
            ])->values()->all(),
        ]);
    }

    public function providers(): Response
    {
        $providers = Provider::published()
            ->withCount(['services' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_available_now')
            ->orderBy('id')
            ->get();

        return Inertia::render('Public/Providers', [
            'providers' => $providers->map(fn (Provider $provider) => [
                'id' => $provider->id,
                'slug' => $provider->slug,
                'name' => $provider->public_name,
                'bio' => $provider->bio,
                'photo' => MediaUrl::resolve($provider->avatar_photo_url),
                'servicesCount' => $provider->services_count,
                'availableNow' => $provider->is_available_now,
                'mobile' => $provider->is_mobile,
            ])->values()->all(),
        ]);
    }

    public function profile(Provider $provider): Response
    {
        abort_if($provider->published_at === null, 404);

        $provider->load([
            'photos',
            'services' => fn ($query) => $query->where('is_active', true)
                ->with('serviceType:id,icon')
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        return Inertia::render('Public/Profile', [
            'provider' => [
                'slug' => $provider->slug,
                'name' => $provider->public_name,
                'bio' => $provider->bio,
                'availableNow' => $provider->is_available_now,
                'bannerPhoto' => MediaUrl::resolve($provider->banner_photo_url),
                'avatarPhoto' => MediaUrl::resolve($provider->avatar_photo_url),
                'location' => $this->locationFor($provider),
                'social' => array_filter([
                    'whatsapp' => $provider->whatsapp_url,
                    'instagram' => $provider->instagram_url,
                    'tiktok' => $provider->tiktok_url,
                    'facebook' => $provider->facebook_url,
                ]),
                'gallery' => $provider->photos->pluck('url')->map(fn (string $url) => MediaUrl::resolve($url))->values()->all(),
                'services' => $provider->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'icon' => $service->icon->value,
                    'name' => $service->name,
                    'duration' => $service->duration_label,
                    'price' => $service->price,
                ])->values()->all(),
            ],
        ]);
    }

    public function booking(Request $request, Provider $provider, Service $service, GenerateAvailableSlots $slots): Response
    {
        abort_unless($provider->published_at !== null && $service->is_active, 404);

        $today = $provider->currentTime()->startOfDay();
        $selectedDate = $this->parseDateParam($request->query('date'), $provider->timezone, $today);
        $monthStart = $this->parseMonthParam($request->query('month'), $provider->timezone, $selectedDate);

        return Inertia::render('Public/Booking', [
            'provider' => [
                'slug' => $provider->slug,
                'name' => $provider->public_name,
            ],
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => $service->price,
            ],
            'selectedDate' => $selectedDate->toDateString(),
            'slots' => collect($slots->handle($provider, $service, $selectedDate))
                ->map(fn (int $minute) => ['h' => intdiv($minute, 60), 'm' => $minute % 60])
                ->values()
                ->all(),
            'monthAvailability' => $slots->forMonth($provider, $service, $monthStart),
        ]);
    }

    public function signIn(): Response
    {
        return Inertia::render('Auth/SignIn');
    }

    public function signUp(): Response
    {
        return Inertia::render('Auth/SignUp');
    }

    /**
     * The one-time "set a password" step for an account that arrived via
     * Google and doesn't have one. EnsureUserHasPassword is what actually
     * redirects here; this just renders it.
     */
    public function createPassword(): Response
    {
        return Inertia::render('Auth/CreatePassword');
    }

    /**
     * Fortify's own view-rendering routes are disabled (config('fortify.views')
     * is false — this app renders every auth screen itself via Inertia), so
     * 'verification.notice' has to be registered here instead. The name must
     * stay exactly that: EnsureEmailIsVerified hardcodes it as the redirect
     * target for unverified users.
     */
    public function verifyEmail(Request $request): Response
    {
        return Inertia::render('Auth/VerifyEmail', [
            'email' => $request->user()->email,
        ]);
    }

    /**
     * Builds Profile.vue's `location` object, which the template renders
     * unconditionally (no v-if) — both branches must always yield a
     * non-empty string.
     *
     * @return array{title: string, subtitle: string}
     */
    private function locationFor(Provider $provider): array
    {
        $title = $provider->is_mobile ? __('provider.mobile_service') : __('provider.in_studio');

        if ($provider->is_available_now) {
            $title .= ' · '.__('provider.available_now');
        }

        $subtitle = match (true) {
            $provider->is_mobile && $provider->service_area !== null => __('provider.at_your_location_area', ['area' => $provider->service_area]),
            $provider->is_mobile => __('provider.at_your_location'),
            $provider->address_line !== null => $provider->address_line,
            default => __('provider.address_after_confirmation'),
        };

        return ['title' => $title, 'subtitle' => $subtitle];
    }

    private function parseDateParam(?string $date, string $timezone, CarbonImmutable $today): CarbonImmutable
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();

                return $parsed->lt($today) ? $today : $parsed;
            } catch (\Exception) {
                // fall through to today
            }
        }

        return $today;
    }

    private function parseMonthParam(?string $month, string $timezone, CarbonImmutable $fallback): CarbonImmutable
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01", $timezone)->startOfMonth();
            } catch (\Exception) {
                // fall through to fallback
            }
        }

        return $fallback->startOfMonth();
    }
}
