<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Provider;
use App\Models\Sale;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The register: manual sales the professional records at the counter. It
 * moves no money — cash, Zelle or the card terminal already did — it keeps
 * the day's honest total, tip and method included.
 */
class SalesController extends Controller
{
    /**
     * How far back the register shows. Bookkeeping older than this belongs
     * in an export, not an endless scroll.
     */
    private const SALES_WINDOW_DAYS = 60;

    public function index(Request $request): Response
    {
        $provider = $request->user()->provider;
        $today = $provider->currentTime()->startOfDay();

        $sales = $provider->sales()
            ->where('created_at', '>=', $today->subDays(self::SALES_WINDOW_DAYS))
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Ventas', [
            'providerName' => $provider->public_name,
            'sales' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'clientName' => $sale->client_name,
                'clientId' => $sale->client_id,
                'amount' => (float) $sale->amount,
                'tip' => (float) $sale->tip,
                'paymentMethod' => $sale->payment_method,
                // Raw UTC — the frontend groups by local day and formats with
                // the viewer's preferences, same contract as the agenda.
                'createdAt' => $sale->created_at->toIso8601String(),
            ])->values()->all(),
            'acceptedMethods' => $provider->payment_methods ?? [],
            'allMethods' => Sale::PAYMENT_METHODS,
            // El buscador de la hoja — la libreta es pequeña. Lleva teléfono
            // porque el buscador lo pinta debajo del nombre y porque se puede
            // buscar por él: ella se acuerda de uno o del otro.
            'clients' => $provider->clients()->orderBy('name')->get(['id', 'name', 'phone'])
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'phone' => $client->phone === null ? null : Format::usPhone($client->phone),
                ])->values()->all(),
            'prefill' => $this->prefillFrom($request, $provider),
        ]);
    }

    /**
     * Arriving from "Registrar venta" on a confirmed appointment. Appointments
     * don't carry a client_id (they're a snapshot, not a relation), so the
     * client is resolved by phone here — the sheet opens with her and the
     * price already in it instead of making the professional look her up twice.
     */
    private function prefillFrom(Request $request, Provider $provider): ?array
    {
        $phone = $request->query('clientPhone');
        $amount = $request->query('amount');

        if ($phone === null && $amount === null) {
            return null;
        }

        return [
            'clientId' => $phone !== null ? $provider->clients()->where('phone', $phone)->value('id') : null,
            'amount' => $amount !== null && is_numeric($amount) ? (float) $amount : null,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'clientId' => ['nullable', 'integer'],
            // A walk-in not in the book yet. Given alongside a phone, she
            // becomes a real card — same as adding her from Clientas — so
            // next time she is a pick, not a name to retype.
            'clientName' => ['nullable', 'string', 'max:120'],
            'clientPhone' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'tip' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            // Only what she actually accepts: the register never offers a
            // method the professional turned off.
            'paymentMethod' => ['required', Rule::in($provider->payment_methods ?? [])],
        ]);

        // Resolved through her own book, so a foreign id is simply "nobody"
        // instead of a probe.
        $client = isset($validated['clientId'])
            ? $provider->clients()->whereKey($validated['clientId'])->first()
            : null;

        $typedName = trim((string) ($validated['clientName'] ?? ''));
        $phoneDigits = Format::digitsOnly($validated['clientPhone'] ?? '');
        $phoneDigits = strlen($phoneDigits) === 10 ? $phoneDigits : null;

        // A phone is what turns a typed name into a real card — same rule as
        // ClientController::import ("no usable phone, no card"): a nameless
        // walk-in with no phone stays a ledger-only snapshot, exactly as
        // before, instead of cluttering the book with cards nothing can ever
        // match again. A phone already on the book reuses that card instead
        // of splitting her history across two (ClientController::store's
        // same dedup rule).
        if ($client === null && $typedName !== '' && $phoneDigits !== null) {
            $client = $provider->clients()->firstOrCreate(['phone' => $phoneDigits], ['name' => $typedName]);
        }

        $provider->sales()->create([
            'client_id' => $client?->id,
            'client_name' => $client?->name ?? ($typedName !== '' ? $typedName : null),
            'amount' => round((float) $validated['amount'], 2),
            'tip' => round((float) ($validated['tip'] ?? 0), 2),
            'payment_method' => $validated['paymentMethod'],
        ]);

        return to_route('admin.ventas')->with('success', 'admin.saleCreated');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $sale->delete();

        return to_route('admin.ventas')->with('success', 'admin.saleDeleted');
    }

    /**
     * Which payment methods this provider accepts. At least one: a register
     * with nothing to offer cannot record anything.
     */
    public function updateMethods(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*' => ['string', Rule::in(Sale::PAYMENT_METHODS)],
        ]);

        $request->user()->provider->update([
            'payment_methods' => array_values(array_unique($validated['methods'])),
        ]);

        return to_route('admin.ventas')->with('success', 'admin.saleMethodsSaved');
    }
}
