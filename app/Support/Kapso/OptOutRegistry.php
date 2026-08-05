<?php

namespace App\Support\Kapso;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Who has asked not to be written to any more.
 *
 * Meta's messaging policy requires honouring an opt-out however it arrives, so
 * this is checked before anything else happens with a message — before the
 * assistant, before the queue does any work.
 *
 * Recognition is deliberately keyword-based and exact-whole-message, never
 * left to the model. Two reasons: an opt-out must not depend on an LLM being
 * available or agreeing, and a fuzzy match here would be dangerous in the
 * other direction — "quiero cancelar mi cita" contains "cancelar" but is a
 * booking request, and treating it as an opt-out would silently cut a client
 * off from the salon.
 */
class OptOutRegistry
{
    private const TABLE = 'whatsapp_opt_outs';

    /** @var list<string> */
    private const OPT_OUT_PHRASES = [
        'stop', 'baja', 'darme de baja', 'darse de baja', 'unsubscribe',
        'no molestar', 'no me escriban', 'no me escribas', 'dejen de escribirme',
        'deja de escribirme', 'cancelar suscripcion', 'cancela la suscripcion',
        'remove me', 'no contactar',
    ];

    /** @var list<string> */
    private const OPT_IN_PHRASES = ['start', 'alta', 'volver a recibir', 'resume'];

    public function optedOut(string $phoneNumberId, string $phone): bool
    {
        return DB::table(self::TABLE)
            ->where('phone_number_id', $phoneNumberId)
            ->where('phone', $phone)
            ->exists();
    }

    public function optOut(string $phoneNumberId, string $phone): void
    {
        try {
            DB::table(self::TABLE)->insert([
                'phone_number_id' => $phoneNumberId,
                'phone' => $phone,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            // Already opted out. Asking twice is not an error.
            if (! in_array((string) $exception->getCode(), ['23505', '23000'], true)) {
                throw $exception;
            }
        }
    }

    public function optIn(string $phoneNumberId, string $phone): void
    {
        DB::table(self::TABLE)
            ->where('phone_number_id', $phoneNumberId)
            ->where('phone', $phone)
            ->delete();
    }

    public static function isOptOutRequest(string $text): bool
    {
        return in_array(self::normalise($text), self::OPT_OUT_PHRASES, true);
    }

    public static function isOptInRequest(string $text): bool
    {
        return in_array(self::normalise($text), self::OPT_IN_PHRASES, true);
    }

    /**
     * Lowercases, drops accents and punctuation and collapses whitespace, so
     * "STOP.", "Stop" and " stop " are one phrase — while still requiring the
     * whole message to be that phrase.
     */
    private static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
