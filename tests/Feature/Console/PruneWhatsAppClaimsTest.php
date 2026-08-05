<?php

namespace Tests\Feature\Console;

use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneWhatsAppClaimsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_old_claims_and_keeps_recent_ones(): void
    {
        $deduplicator = app(WebhookDeduplicator::class);

        $deduplicator->claim(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.recent');
        $deduplicator->claim(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.ancient');

        DB::table('whatsapp_idempotency_keys')
            ->where('idempotency_key', 'wamid.ancient')
            ->update(['created_at' => now()->subDays(30)]);

        $this->artisan('whatsapp:prune-claims')->assertSuccessful();

        // The recent one still has to stop a duplicate; the old one cannot.
        $this->assertTrue($deduplicator->claimed(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.recent'));
        $this->assertFalse($deduplicator->claimed(WebhookDeduplicator::SCOPE_MESSAGE, 'wamid.ancient'));
    }

    /**
     * A claim from within the retry window must survive, or a Kapso retry could
     * answer the same client twice.
     */
    public function test_a_claim_from_within_the_retry_window_survives(): void
    {
        $deduplicator = app(WebhookDeduplicator::class);
        $deduplicator->claim(WebhookDeduplicator::SCOPE_REPLY, 'wamid.yesterday');

        DB::table('whatsapp_idempotency_keys')
            ->where('idempotency_key', 'wamid.yesterday')
            ->update(['created_at' => now()->subDays(6)]);

        $this->artisan('whatsapp:prune-claims')->assertSuccessful();

        $this->assertTrue($deduplicator->claimed(WebhookDeduplicator::SCOPE_REPLY, 'wamid.yesterday'));
    }
}
