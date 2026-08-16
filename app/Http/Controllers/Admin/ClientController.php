<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Support\Format;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The provider's client book. A card holds what the appointment history
 * cannot: notes, tags, an email — while the history itself stays linked by
 * phone at read time, the same match the WhatsApp assistant uses. Deleting a
 * card therefore never touches a booking.
 */
class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $provider = $request->user()->provider;

        $clients = $provider->clients()->orderBy('name')->orderBy('id')->get();

        // One grouped query for every card's upcoming count — per-card
        // queries would grow linearly with the book.
        $upcomingByPhone = $provider->appointments()->blocking()
            ->where('starts_at', '>=', $provider->currentTime())
            ->whereIn('client_phone', $clients->pluck('phone')->filter()->values())
            ->selectRaw('client_phone, count(*) as upcoming')
            ->groupBy('client_phone')
            ->pluck('upcoming', 'client_phone');

        return Inertia::render('Admin/Clientes', [
            'providerName' => $provider->public_name,
            'clients' => $clients->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone === null ? null : Format::usPhone($client->phone),
                'tags' => $client->tags,
                'upcomingCount' => (int) ($upcomingByPhone[$client->phone] ?? 0),
            ])->values()->all(),
        ]);
    }

    public function show(Request $request, Client $client): Response
    {
        $provider = $request->user()->provider;
        $now = $provider->currentTime();

        $upcoming = $client->appointmentsQuery()->blocking()
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->get();

        $past = $client->appointmentsQuery()
            ->where('starts_at', '<', $now)
            ->orderByDesc('starts_at')
            ->limit(30)
            ->get();

        return Inertia::render('Admin/Cliente', [
            'providerName' => $provider->public_name,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone === null ? null : Format::usPhone($client->phone),
                'phoneDigits' => $client->phone,
                'email' => $client->email,
                'notes' => $client->notes,
                'tags' => $client->tags,
            ],
            'photos' => $client->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => MediaUrl::resolve($photo->url),
            ])->values()->all(),
            'maxPhotos' => Client::MAX_PHOTOS,
            'upcoming' => $upcoming->map(fn (Appointment $appointment) => $this->appointmentPayload($appointment))->values()->all(),
            'past' => $past->map(fn (Appointment $appointment) => $this->appointmentPayload($appointment))->values()->all(),
            'stats' => [
                'upcoming' => $upcoming->count(),
                'completed' => $client->appointmentsQuery()->where('status', AppointmentStatus::Closed)->count(),
                'cancelled' => $client->appointmentsQuery()->where('status', AppointmentStatus::Cancelled)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $data = $this->validated($request);

        if ($data['phone'] !== null && $provider->clients()->where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages(['clientPhone' => __('admin.clientPhoneTaken')]);
        }

        $client = $provider->clients()->create($data);

        return to_route('admin.clientes.show', $client)->with('success', 'admin.clientCreated');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $provider = $request->user()->provider;

        $data = $this->validated($request);

        if ($data['phone'] !== null
            && $provider->clients()->where('phone', $data['phone'])->whereKeyNot($client->id)->exists()) {
            throw ValidationException::withMessages(['clientPhone' => __('admin.clientPhoneTaken')]);
        }

        $client->update($data);

        return to_route('admin.clientes.show', $client)->with('success', 'admin.clientUpdated');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return to_route('admin.clientes')->with('success', 'admin.clientDeleted');
    }

    /**
     * Bulk import from the phone's contact picker (Contact Picker API).
     * The browser hands over only what the professional selected; here each
     * pick becomes a card if it carries a usable US phone the book does not
     * already know. No usable phone, no card — a card the assistant and the
     * agenda can never match by phone would only clutter the book.
     */
    public function import(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'contacts' => ['required', 'array', 'max:500'],
            'contacts.*.name' => ['required', 'string', 'max:120'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
        ]);

        $known = $provider->clients()->pluck('phone')->filter()->flip();
        $imported = 0;

        foreach ($validated['contacts'] as $contact) {
            $digits = Format::digitsOnly($contact['phone'] ?? '');

            if (strlen($digits) !== 10 || isset($known[$digits])) {
                continue;
            }

            $provider->clients()->create([
                'name' => trim($contact['name']),
                'phone' => $digits,
            ]);
            $known[$digits] = true;
            $imported++;
        }

        return to_route('admin.clientes')->with('success', 'admin.clientsImported');
    }

    /**
     * Shared by store and update: the same live-masked US phone the rest of
     * the panel uses, normalized to ten digits or null — never empty string,
     * so the unique (provider_id, phone) pair only ever sees real numbers.
     *
     * @return array{name: string, phone: ?string, email: ?string, notes: ?string, tags: list<string>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'clientName' => ['required', 'string', 'max:120'],
            'clientPhone' => ['nullable', 'string', 'max:30'],
            'clientEmail' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tags' => ['array', 'max:10'],
            // Nullable: the framework converts empty strings to null before
            // validation, and an emptied chip must not fail the whole card.
            'tags.*' => ['nullable', 'string', 'max:24'],
        ]);

        $phoneDigits = Format::digitsOnly($validated['clientPhone'] ?? '');

        if ($phoneDigits !== '' && strlen($phoneDigits) !== 10) {
            throw ValidationException::withMessages(['clientPhone' => __('admin.phoneInvalid')]);
        }

        return [
            'name' => trim($validated['clientName']),
            'phone' => $phoneDigits === '' ? null : $phoneDigits,
            'email' => $validated['clientEmail'] ?? null,
            'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
            'tags' => array_values(array_unique(array_filter(array_map(
                static fn (?string $tag): string => trim((string) $tag),
                $validated['tags'] ?? [],
            ), static fn (string $tag): bool => $tag !== ''))),
        ];
    }

    /**
     * @return array{id: int, service: string, price: int, durationMinutes: int, status: string, startsAt: string}
     */
    private function appointmentPayload(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'service' => $appointment->service_name,
            'price' => $appointment->price,
            'durationMinutes' => $appointment->duration_minutes,
            'status' => $appointment->status->value,
            // Raw UTC — the frontend formats it with the viewer's language
            // and 12h/24h preference, same contract as the agenda.
            'startsAt' => $appointment->starts_at->toIso8601String(),
        ];
    }
}
