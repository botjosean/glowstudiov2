<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Lead;
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

    /**
     * The activation panel: a checklist of everything a provider needs before
     * their page can take real bookings. Every signal is derived from data
     * that already exists — no onboarding state is stored anywhere.
     */
    public function inicio(Request $request): Response
    {
        $provider = $request->user()->provider;
        $today = $provider->currentTime();

        return Inertia::render('Admin/Inicio', [
            'providerName' => $provider->public_name,
            'avatarPhoto' => MediaUrl::resolve($provider->avatar_photo_url),
            'publicUrl' => route('providers.show', $provider),
            'checklist' => [
                'profileComplete' => filled($provider->bio)
                    && ($provider->is_mobile ? filled($provider->service_area) : filled($provider->address_line)),
                // The grid on the public profile is three across, so six is
                // what makes it read as two full rows instead of a ragged one.
                // Counted rather than booleaned so the step can say how many
                // are still missing.
                'photoCount' => $provider->photos()->count(),
                'photosNeeded' => Provider::MAX_GALLERY_PHOTOS,
                'hasActiveServices' => $provider->services()->active()->exists(),
                'whatsappConnected' => $provider->whatsapp_phone_number_id !== null,
                'published' => $provider->published_at !== null,
                'hasAppointments' => $provider->appointments()->exists(),
            ],
            'summary' => [
                'todayCount' => $provider->appointments()->blocking()
                    ->whereBetween('starts_at', [$today->startOfDay(), $today->endOfDay()])
                    ->count(),
                'pendingCount' => $provider->appointments()
                    ->where('status', AppointmentStatus::Pending)
                    ->count(),
            ],
        ]);
    }

    public function citas(Request $request): Response
    {
        $provider = $request->user()->provider;
        $provider->load(['businessHours', 'timeOff']);

        // Snapshot columns mean this is the whole query — no eager loading needed.
        $appointments = $provider->appointments()
            ->where('starts_at', '>=', $provider->currentTime()->subDays(self::APPOINTMENTS_WINDOW_DAYS)->startOfDay())
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Admin/Citas', [
            'providerName' => $provider->public_name,
            // The agenda timeline shades everything outside the working window,
            // so it needs the same rows the Horario page edits — raw data, not
            // derived flags, because the window depends on which date the
            // viewer has selected and only the client knows that.
            'schedule' => [
                'lunchStart' => $provider->lunch_start_minute,
                'lunchEnd' => $provider->lunch_end_minute,
                'days' => $this->weekScheduleFor($provider),
                'timeOff' => $provider->timeOff->map(fn ($off) => [
                    'startsOn' => $off->starts_on->toDateString(),
                    'endsOn' => $off->ends_on->toDateString(),
                    'reason' => $off->reason,
                ])->values()->all(),
            ],
            // For the create-appointment sheet: what she can book by hand.
            'services' => $provider->services()->active()->orderBy('position')->orderBy('id')
                ->get()->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'durationMinutes' => $service->duration_minutes,
                    'price' => $service->price,
                ])->values()->all(),
            // The sheet's "Seleccionar clienta" picker — the whole book, it
            // is small. phoneDigits feeds the form; phone is what she reads.
            'clients' => $provider->clients()->orderBy('name')->get()
                ->map(fn ($client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'phone' => $client->phone === null ? null : Format::usPhone($client->phone),
                    'phoneDigits' => $client->phone,
                ])->values()->all(),
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
                'atHome' => $appointment->at_home,
                'clientAddress' => $appointment->client_address,
                // Raw UTC — the frontend formats it with the viewer's
                // language and 12h/24h preference (both client-only).
                'startsAt' => $appointment->starts_at->toIso8601String(),
            ])->values()->all(),
            // People who wrote on WhatsApp and are still waiting for a person.
            // Only present for a receptionist: an agent books them itself, so a
            // waiting list would be a list of nothing.
            'leads' => $provider->botIsReceptionist() ? $this->waitingLeadsFor($provider) : [],
        ]);
    }

    /**
     * The WhatsApp requests nobody has dealt with, oldest first.
     *
     * Oldest first on purpose, unlike every other list in this panel: this one
     * is a queue of people waiting, and the one who has waited longest is the
     * one about to give up.
     *
     * @return list<array<string, mixed>>
     */
    private function waitingLeadsFor(Provider $provider): array
    {
        return $provider->leads()->waiting()
            ->orderBy('first_contact_at')
            ->limit(50)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => Format::usPhone($lead->phone),
                // Feeds the "Crear cita" prefill, same shape the agenda uses.
                'phoneDigits' => $lead->phone,
                'message' => $lead->message,
                'firstContactAt' => $lead->first_contact_at->toIso8601String(),
                'lastContactAt' => $lead->last_contact_at->toIso8601String(),
            ])->values()->all();
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
                'homeAvailable' => $service->home_available,
                'upcomingCount' => (int) $service->upcoming_count,
            ])->values()->all(),
        ]);
    }

    public function horario(Request $request): Response
    {
        $provider = $request->user()->provider;

        $provider->load(['businessHours', 'timeOff']);

        return Inertia::render('Admin/Horario', [
            'providerName' => $provider->public_name,
            'schedule' => [
                'lunchStart' => $provider->lunch_start_minute,
                'lunchEnd' => $provider->lunch_end_minute,
                'bufferMinutes' => $provider->buffer_minutes,
                'days' => $this->weekScheduleFor($provider),
            ],
            'timeOff' => $provider->timeOff->map(fn ($off) => [
                'id' => $off->id,
                'startsOn' => $off->starts_on->toDateString(),
                'endsOn' => $off->ends_on->toDateString(),
                'reason' => $off->reason,
            ])->values()->all(),
        ]);
    }

    /**
     * The seven weekday rows both Citas (timeline shading) and Horario (the
     * editing form) render. Always seven, built from the range rather than
     * from the rows, so a provider whose rows are somehow incomplete still
     * gets a full week instead of a day silently missing.
     *
     * @return list<array{weekday: int, isOpen: bool, workStart: int, workEnd: int}>
     */
    private function weekScheduleFor(Provider $provider): array
    {
        return array_map(function (int $weekday) use ($provider) {
            $hours = $provider->businessHours->firstWhere('weekday', $weekday);

            return [
                'weekday' => $weekday,
                'isOpen' => $hours?->is_open ?? true,
                'workStart' => $hours?->work_start_minute ?? $provider->work_start_minute,
                'workEnd' => $hours?->work_end_minute ?? $provider->work_end_minute,
            ];
        }, range(0, 6));
    }

    /**
     * The Perfil tab, Booksy-style: a showcase of the business as the world
     * sees it — cover, progress, numbers, portfolio, bot — with editing moved
     * to Configuración → Información del negocio (negocio() below).
     */
    public function perfil(Request $request): Response
    {
        $provider = $request->user()->provider;
        $provider->load('photos');
        $now = $provider->currentTime();
        $monthStart = $now->startOfMonth();

        $checklist = $this->checklistFor($provider);

        return Inertia::render('Admin/Perfil', [
            'providerName' => $provider->public_name,
            'bannerPhoto' => MediaUrl::resolve($provider->banner_photo_url),
            'avatarPhoto' => MediaUrl::resolve($provider->avatar_photo_url),
            'publicName' => $provider->public_name,
            'addressLabel' => $provider->is_mobile ? $provider->service_area : $provider->address_line,
            'published' => $provider->published_at !== null,
            'publicUrl' => route('providers.show', $provider),
            'whatsappConnected' => $provider->whatsapp_phone_number_id !== null,
            'progress' => [
                'done' => count(array_filter($checklist)),
                'total' => count($checklist),
            ],
            'stats' => [
                'upcoming' => $provider->appointments()->blocking()
                    ->where('starts_at', '>=', $now)->count(),
                'completedMonth' => $provider->appointments()
                    ->where('status', AppointmentStatus::Closed)
                    ->where('starts_at', '>=', $monthStart)->count(),
                'salesMonth' => (float) $provider->sales()
                    ->where('created_at', '>=', $monthStart)
                    ->selectRaw('COALESCE(SUM(amount + tip), 0) as total')->value('total'),
            ],
            'gallery' => $provider->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => MediaUrl::resolve($photo->url),
            ])->values()->all(),
            'maxGallery' => Provider::MAX_GALLERY_PHOTOS,
        ]);
    }

    /**
     * Everything the checklist counts, in one place: inicio() renders the
     * long form, perfil() only needs done/total for the progress card.
     *
     * @return array<string, bool>
     */
    private function checklistFor(Provider $provider): array
    {
        return [
            'account' => true,
            'profile' => filled($provider->bio)
                && ($provider->is_mobile ? filled($provider->service_area) : filled($provider->address_line)),
            'photos' => $provider->photos()->count() >= Provider::MAX_GALLERY_PHOTOS,
            'services' => $provider->services()->active()->exists(),
            'whatsapp' => $provider->whatsapp_phone_number_id !== null,
            'published' => $provider->published_at !== null,
            'booked' => $provider->appointments()->exists(),
        ];
    }

    /**
     * Configuración → Información del negocio: the editing form that used to
     * masquerade as the Perfil tab. Same update endpoints as always.
     */
    public function negocio(Request $request): Response
    {
        $provider = $request->user()->provider;
        $provider->load('photos');

        return Inertia::render('Admin/Negocio', [
            'profile' => [
                'username' => $request->user()->username,
                'publicName' => $provider->public_name,
                'phone' => Format::usPhone((string) $request->user()->phone),
                'email' => $request->user()->email,
                'bio' => $provider->bio,
                'isMobile' => $provider->is_mobile,
                'homeService' => $provider->home_service,
                'serviceArea' => $provider->service_area,
                'addressLine' => $provider->address_line,
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
            // Same link the Inicio checklist shows — the profile page is where
            // providers go looking for it when they want to send it to someone.
            'publicUrl' => route('providers.show', $provider),
        ]);
    }

    public function ajustes(Request $request): Response
    {
        $support = (string) config('services.support.whatsapp');

        return Inertia::render('Admin/Ajustes', [
            'providerName' => $request->user()->provider->public_name,
            // Where the help pill goes. Null until a support number is set, and
            // the pill says so rather than opening nothing — a help centre is
            // its own piece of work.
            'supportUrl' => $support === '' ? null : 'https://wa.me/'.preg_replace('/\D/', '', $support),
        ]);
    }
}
