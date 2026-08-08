<?php

namespace App\Support;

class Format
{
    /**
     * Mirrors resources/js/composables/useFormat.js formatDuration() exactly,
     * so public (server-formatted) and admin (client-formatted) pages agree.
     */
    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours}h" : "{$hours}h {$rest} min";
    }

    /**
     * Minutes from local midnight as a wall clock: 660 -> "11:00".
     *
     * 24-hour on purpose. This one is read by the assistant, not by a person
     * with a time-format preference, and "11:00 a 22:00" leaves no room for
     * the am/pm mistake that "11:00 a 10:00" invites.
     */
    public static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * "3055550199" -> "(305) 555-0199"
     */
    public static function usPhone(string $digits): string
    {
        if (strlen($digits) !== 10) {
            return $digits;
        }

        return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
    }

    /**
     * Strips everything but digits, and drops a leading US country code (1)
     * from an 11-digit input so "(305) 555-0123", "+13055550123" and
     * "13055550123" all normalize to the same 10 digits.
     */
    public static function digitsOnly(string $input): string
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }
}
