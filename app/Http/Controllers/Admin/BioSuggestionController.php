<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Assistant\AssistantUnavailable;
use App\Support\Assistant\ChatModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BioSuggestionController extends Controller
{
    /**
     * Writes two bios from a handful of taps, so nobody has to sit in front of
     * an empty textarea deciding how to describe themselves.
     *
     * The answers are the only thing that reaches the model, and they come out
     * of fixed choices in the panel rather than free text — there is nothing
     * here for a prompt injection to ride in on, and no provider data is sent.
     * The result is a suggestion: it lands in the textarea for the provider to
     * edit and is not saved until they save the profile themselves.
     */
    public function __invoke(Request $request, ChatModel $model): JsonResponse
    {
        $data = $request->validate([
            'area' => ['required', 'string', 'max:40'],
            'experience' => ['required', 'string', 'max:40'],
            'highlights' => ['nullable', 'array', 'max:5'],
            'highlights.*' => ['string', 'max:40'],
            'city' => ['nullable', 'string', 'max:60'],
        ]);

        $locale = app()->getLocale() === 'en' ? 'inglés' : 'español';
        $highlights = implode(', ', $data['highlights'] ?? []) ?: 'nada en particular';
        $city = $data['city'] ?: 'Atlanta';

        try {
            $answer = $model->chat([
                [
                    'role' => 'system',
                    'content' => <<<PROMPT
                    Escribes biografías cortas para el perfil público de profesionales de belleza.

                    Devuelve EXACTAMENTE dos opciones, una por línea, sin numerarlas, sin comillas
                    y sin ningún texto adicional. Cada una:
                    - En primera persona y en {$locale}.
                    - Máximo 160 caracteres.
                    - Cálida y profesional, sin sonar a anuncio ni a currículum.
                    - Como mucho un emoji, y solo si encaja.
                    - Nada de precios, horarios ni promesas de resultados.
                    PROMPT,
                ],
                [
                    'role' => 'user',
                    'content' => "Rubro: {$data['area']}. Experiencia: {$data['experience']}. "
                        ."Lo que la distingue: {$highlights}. Ciudad: {$city}.",
                ],
            ], []);
        } catch (AssistantUnavailable $exception) {
            // The provider is mid-onboarding; a failed suggestion must not look
            // like the profile itself is broken.
            return response()->json(['suggestions' => []], 503);
        }

        $suggestions = collect(preg_split('/\R/', (string) ($answer['content'] ?? '')))
            ->map(fn (string $line) => trim($line, " \t\"'-•*"))
            ->filter(fn (string $line) => mb_strlen($line) >= 20)
            ->take(2)
            ->values()
            ->all();

        return response()->json(['suggestions' => $suggestions]);
    }
}
