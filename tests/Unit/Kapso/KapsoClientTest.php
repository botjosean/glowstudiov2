<?php

namespace Tests\Unit\Kapso;

use App\Support\Kapso\KapsoClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KapsoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.kapso.api_key' => 'test-api-key',
        ]);
    }

    /**
     * Kapso ignores an unrecognised query parameter instead of rejecting it, so
     * the wrong name here does not fail loudly: it silently returns the
     * account's latest messages instead of this conversation's, and both the
     * model's history and the human hand-off guard read them as if they were.
     * Verified directly against Kapso before fixing it: a nonexistent
     * conversation id still returned rows under the old name.
     */
    public function test_it_asks_kapso_for_this_conversation_by_the_right_parameter(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['data' => []])]);

        (new KapsoClient)->recentMessages('conv_123', 8);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'platform/v1/whatsapp/messages')
                && str_contains($request->url(), 'conversation_id=conv_123')
                && ! str_contains($request->url(), 'whatsapp_conversation_id');
        });
    }

    public function test_it_reverses_kapsos_newest_first_order_to_a_conversational_one(): void
    {
        Http::fake(['api.kapso.ai/*' => Http::response(['data' => [
            ['id' => 'wamid.2', 'timestamp' => '200', 'text' => ['body' => 'segundo'], 'kapso' => ['direction' => 'outbound']],
            ['id' => 'wamid.1', 'timestamp' => '100', 'text' => ['body' => 'primero'], 'kapso' => ['direction' => 'inbound']],
        ]])]);

        $turns = (new KapsoClient)->recentMessages('conv_123', 8);

        $this->assertSame(['wamid.1', 'wamid.2'], array_column($turns, 'id'));
    }
}
