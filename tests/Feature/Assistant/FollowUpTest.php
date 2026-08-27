<?php

namespace Tests\Feature\Assistant;

use App\Console\Commands\FollowUpWaitingLeads;
use App\Models\Lead;
use App\Models\Provider;
use App\Support\Kapso\OptOutRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FreezesBusinessHours;
use Tests\TestCase;

/**
 * The second message arriving on a clock, for a client who asked one thing and
 * then went quiet.
 *
 * Most of these are about the four things that must be true before a word is
 * sent — each one is a way of talking to somebody who did not want to be
 * talked to.
 */
class FollowUpTest extends TestCase
{
    use FreezesBusinessHours;
    use RefreshDatabase;

    private const NUMBER = '868324373028256';

    protected function setUp(): void
    {
        parent::setUp();

        // A follow-up is still the bot speaking, so it is gated by the same
        // bot hours the receptionist is — none of these tests mean to be
        // about that (see FreezesBusinessHours).
        $this->freezeToBusinessHours();

        config([
            'services.kapso.api_key' => 'test-api-key',
            'services.kapso.base_url' => 'https://api.kapso.ai',
            'services.kapso.graph_version' => 'v24.0',
            'services.assistant.receptionist_follow_up_minutes' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        $this->unfreezeClock();

        parent::tearDown();
    }

    public function test_it_follows_up_after_ten_minutes_of_silence(): void
    {
        $this->fakeKapso();
        $lead = $this->waitingLead();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/meta/')
            && $request['to'] === '13055550142'
            && str_contains($request['text']['body'], 'no te olvidamos')
            && str_contains($request['text']['body'], 'foto'));

        $this->assertSame(2, $lead->fresh()->bot_messages_sent);
    }

    public function test_it_waits_the_full_ten_minutes(): void
    {
        $this->fakeKapso();
        $this->waitingLead(silentFor: 4);

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
    }

    /**
     * A timer that talks over the professional in front of her own client is
     * the complaint that started the whole receptionist idea.
     */
    public function test_it_stays_quiet_when_the_professional_already_answered(): void
    {
        // An outbound turn WITH a `from` is a message typed on the salon's own
        // phone — see KapsoClient::recentMessages.
        $this->fakeKapso(history: [[
            'id' => 'wamid.h1',
            'from' => '14044518022',
            'timestamp' => CarbonImmutable::now()->getTimestamp(),
            'text' => ['body' => 'Hola mi amor, ya te atiendo'],
            'kapso' => ['direction' => 'outbound'],
        ]]);
        $lead = $this->waitingLead();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));

        // And the card closes itself, so the panel stops showing her as waiting.
        $lead->refresh();
        $this->assertSame(Lead::STATUS_HANDLED, $lead->status);
        $this->assertNotNull($lead->answered_at);
    }

    /**
     * Our own greeting comes back in the history too (outbound, no `from`).
     * Reading that as "somebody answered" would silence every follow-up there
     * has ever been.
     */
    public function test_the_bots_own_greeting_does_not_count_as_an_answer(): void
    {
        $this->fakeKapso(history: [[
            'id' => 'wamid.h1',
            'timestamp' => CarbonImmutable::now()->getTimestamp(),
            'text' => ['body' => 'Soy el asistente de WhatsApp de Pati'],
            'kapso' => ['direction' => 'outbound'],
        ]]);
        $this->waitingLead();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
    }

    public function test_it_never_writes_to_somebody_who_asked_to_be_left_alone(): void
    {
        $this->fakeKapso();
        $this->waitingLead();

        app(OptOutRegistry::class)->optOut(self::NUMBER, '13055550142');

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
    }

    public function test_a_conversation_that_already_had_both_messages_is_left_alone(): void
    {
        $this->fakeKapso();
        $this->waitingLead()->forceFill(['bot_messages_sent' => 2])->save();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
    }

    public function test_a_client_who_got_booked_meanwhile_is_left_alone(): void
    {
        $this->fakeKapso();
        $this->waitingLead()->forceFill([
            'status' => Lead::STATUS_HANDLED,
            'answered_at' => now(),
        ])->save();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
    }

    public function test_an_agent_provider_never_follows_up(): void
    {
        $this->fakeKapso();
        $provider = Provider::factory()->published()->create([
            'whatsapp_phone_number_id' => self::NUMBER,
        ]);
        $this->waitingLead($provider);

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
    }

    /**
     * Unable to tell whether she has been answered: staying quiet is the safe
     * half of that uncertainty.
     */
    public function test_it_stays_quiet_when_the_conversation_cannot_be_read(): void
    {
        Http::fake([
            'api.kapso.ai/platform/*' => Http::response(['error' => 'nope'], 500),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
        ]);
        $lead = $this->waitingLead();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/meta/'));
        $this->assertSame(1, $lead->fresh()->bot_messages_sent);
    }

    /**
     * The count moves before the send, so a Kapso failure costs one client her
     * second message instead of putting one into her phone every minute.
     */
    public function test_a_failed_send_is_not_retried_forever(): void
    {
        Http::fake([
            'api.kapso.ai/platform/*' => Http::response(['data' => []]),
            'api.kapso.ai/meta/*' => Http::response(['error' => 'boom'], 500),
        ]);
        $lead = $this->waitingLead();

        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();
        $this->artisan(FollowUpWaitingLeads::class)->assertSuccessful();

        $this->assertSame(2, $lead->fresh()->bot_messages_sent);
        $this->assertSame(1, $this->sendAttempts());
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    private function fakeKapso(array $history = []): void
    {
        Http::fake([
            'api.kapso.ai/platform/*' => Http::response(['data' => $history]),
            'api.kapso.ai/meta/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
        ]);
    }

    private function waitingLead(?Provider $provider = null, int $silentFor = 30): Lead
    {
        $provider ??= Provider::factory()->published()->receptionist()->create([
            'public_name' => 'Patricia',
            'bot_display_name' => 'Pati',
            'whatsapp_phone_number_id' => self::NUMBER,
        ]);

        return Lead::factory()->for($provider)->create([
            'phone' => '3055550142',
            'bot_messages_sent' => 1,
            'conversation_id' => 'conv_1',
            'last_bot_message_at' => CarbonImmutable::now()->subMinutes($silentFor),
        ]);
    }

    private function sendAttempts(): int
    {
        $count = 0;

        Http::recorded(function (Request $request) use (&$count): void {
            if (str_contains($request->url(), '/meta/')) {
                $count++;
            }
        });

        return $count;
    }
}
