<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Support\Format;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How far back appointments are loaded for the admin list. The Vue side
     * still filters by tab client-side over the whole array, so this is a
     * defensive bound rather than real pagination.
     */
    private const APPOINTMENTS_WINDOW_DAYS = 60;

    public function citas(Request $request): Response
    {
        $provider = $request->user()->provider;

        // Snapshot columns mean this is the whole query — no eager loading needed.
        $appointments = $provider->appointments()
            ->where('starts_at', '>=', $provider->currentTime()->subDays(self::APPOINTMENTS_WINDOW_DAYS)->startOfDay())
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Admin/Citas', [
            'providerName' => $provider->public_name,
            'appointments' => $appointments->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'clientName' => $appointment->client_name,
                'clientPhone' => Format::usPhone($appointment->client_phone),
                // Raw digits for the click-to-chat link; clientPhone above
                // stays display-formatted (a test pins that exact format).
                'clientPhoneDigits' => Format::digitsOnly($appointment->client_phone),
                'service' => $appointment->service_name,
                'provider' => $provider->public_name,
                'durationMinutes' => $appointment->duration_minutes,
                'price' => $appointment->price,
                'status' => $appointment->status->value,
                // Raw UTC — the frontend formats it with the viewer's
                // language and 12h/24h preference (both client-only).
                'startsAt' => $appointment->starts_at->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function servicios(Request $request): Response
    {
        $provider = $request->user()->provider;

        $services = $provider->services()
            ->withCount(['appointments as upcoming_count' => fn ($query) => $query->blocking()->where('starts_at', '>=', now())])
            ->orderBy('position')->orderBy('id')->get();

        return Inertia::render('Admin/Servicios', [
            'providerName' => $provider->public_name,
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'durationMinutes' => $service->duration_minutes,
                'price' => $service->price,
                'category' => $service->category->value,
                'isActive' => $service->is_active,
                'upcomingCount' => (int) $service->upcoming_count,
            ])->values()->all(),
        ]);
    }

    public function horario(Request $request): Response
    {
        $provider = $request->user()->provider;

        return Inertia::render('Admin/Horario', [
            'providerName' => $provider->public_name,
            'schedule' => [
                'workStart' => $provider->work_start_minute,
                'workEnd' => $provider->work_end_minute,
                'lunchStart' => $provider->lunch_start_minute,
                'lunchEnd' => $provider->lunch_end_minute,
                'bufferMinutes' => $provider->buffer_minutes,
            ],
        ]);
    }

    public function perfil(Request $request): Response
    {
        $provider = $request->user()->provider;
        $provider->load('photos');

        return Inertia::render('Admin/Perfil', [
            'profile' => [
                'username' => $request->user()->username,
                'publicName' => $provider->public_name,
                'phone' => Format::usPhone((string) $request->user()->phone),
                'email' => $request->user()->email,
                'bio' => $provider->bio,
                'bannerPhoto' => MediaUrl::resolve($provider->banner_photo_url),
                'avatarPhoto' => MediaUrl::resolve($provider->avatar_photo_url),
                'gallery' => $provider->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => MediaUrl::resolve($photo->url),
                ])->values()->all(),
                'maxGallery' => Provider::MAX_GALLERY_PHOTOS,
                'published' => $provider->published_at !== null,
                'activeServicesCount' => $provider->services()->active()->count(),
            ],
        ]);
    }

    public function ajustes(Request $request): Response
    {
        return Inertia::render('Admin/Ajustes', [
            'providerName' => $request->user()->provider->public_name,
        ]);
    }
}
