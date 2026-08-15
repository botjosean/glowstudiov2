<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Sale;
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
            // For the sheet's "who was it" select — the book is small.
            'clients' => $provider->clients()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name])
                ->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'clientId' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'tip' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            // Only what she actually accepts: the register never offers a
            // method the professional turned off.
            'paymentMethod' => ['required', Rule::in($provider->payment_methods ?? [])],
        ]);

        // Resolved through her own book, so a foreign id is simply "nobody"
        // instead of a probe — and the name is snapshotted for bookkeeping.
        $client = isset($validated['clientId'])
            ? $provider->clients()->whereKey($validated['clientId'])->first()
            : null;

        $provider->sales()->create([
            'client_id' => $client?->id,
            'client_name' => $client?->name,
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
