<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla donde una clienta deja sus estrellas.
 *
 * Pública y sin cuenta, como todo lo que ve una clienta en esta app. Lo que
 * hace de llave es el token del enlace que le llegó por WhatsApp: sin él no se
 * llega, y con él sólo se llega a SU cita.
 *
 * Se puede contestar una sola vez. Una invitación ya contestada enseña un
 * «gracias» en vez del formulario — no un error, porque tocar dos veces el
 * mismo enlace de WhatsApp es lo más normal del mundo y no es culpa de nadie.
 */
class ReviewController extends Controller
{
    public function show(string $token): Response
    {
        $review = Review::with('provider:id,slug,public_name,avatar_photo_url')
            ->where('token', $token)
            ->firstOrFail();

        return Inertia::render('Public/Review', [
            'provider' => [
                'slug' => $review->provider->slug,
                'name' => $review->provider->public_name,
            ],
            'clientName' => $review->client_name,
            'alreadyAnswered' => $review->answered_at !== null,
            'rating' => $review->rating,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $review = Review::where('token', $token)->firstOrFail();

        // Ya contestada: no se sobrescribe. Sin esto, quien conserve el
        // enlace puede cambiar su nota cuando quiera, y una nota que se puede
        // reescribir no le sirve de nada a quien la lee.
        if ($review->answered_at !== null) {
            return to_route('resena.show', $token);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'answered_at' => now(),
        ]);

        return to_route('resena.show', $token);
    }
}
