<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('whatsapp:prune-claims')]
#[Description('Delete WhatsApp idempotency claims older than the retry window.')]
class PruneWhatsAppClaims extends Command
{
    /**
     * A claim only has to outlive the window in which a duplicate could still
     * arrive. Kapso gives up retrying after about two and a half minutes, so a
     * week is already absurdly generous — it is chosen for the operator's peace
     * of mind, not for correctness.
     *
     * Without this the table grows by a few rows per message forever, which is
     * slow rather than broken, and therefore exactly the kind of thing that
     * gets noticed a year later on a 38 GB disk.
     */
    private const KEEP_DAYS = 7;

    public function handle(): int
    {
        $deleted = DB::table('whatsapp_idempotency_keys')
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->delete();

        $this->info("Deleted {$deleted} WhatsApp claim(s) older than ".self::KEEP_DAYS.' days.');

        return self::SUCCESS;
    }
}
