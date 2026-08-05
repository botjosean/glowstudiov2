<?php

namespace App\Support\Kapso;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Single-use claims that make webhook handling idempotent.
 *
 * Kapso retries a delivery at 10s / 40s / 90s until it sees a 200, so the same
 * message arrives more than once as a matter of routine, not as an error. The
 * uniqueness guarantee has to live in the database: two retries can land on
 * two workers at the same instant, and any check-then-act in PHP has a race
 * between the check and the act.
 *
 * Deliberately not an Eloquent model — this table holds no domain entity, has
 * no relationships and is never read as a record, so a model, factory and
 * seeder for it would be three files of ceremony around one INSERT.
 */
class WebhookDeduplicator
{
    /** A whole webhook delivery, keyed by Kapso's X-Idempotency-Key. */
    public const SCOPE_DELIVERY = 'delivery';

    /** One message inside a delivery, keyed by its WAMID. */
    public const SCOPE_MESSAGE = 'message';

    /** A reply already sent for a message, keyed by the inbound WAMID. */
    public const SCOPE_REPLY = 'reply';

    private const TABLE = 'whatsapp_idempotency_keys';

    /**
     * Attempts to take the claim.
     *
     * @return bool true if this caller took it, false if someone already had it
     */
    public function claim(string $scope, string $key): bool
    {
        try {
            DB::table(self::TABLE)->insert([
                'scope' => $scope,
                'idempotency_key' => $key,
                'created_at' => now(),
            ]);

            return true;
        } catch (QueryException $exception) {
            if ($this->isDuplicate($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    public function claimed(string $scope, string $key): bool
    {
        return DB::table(self::TABLE)
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->exists();
    }

    /**
     * 23505 is Postgres' unique_violation; 23000 is what SQLite reports for the
     * same thing, so the test suite behaves like production here.
     */
    private function isDuplicate(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23505', '23000'], true);
    }
}
