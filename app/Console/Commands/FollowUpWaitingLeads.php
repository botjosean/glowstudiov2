<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Provider;
use App\Support\Assistant\Receptionist;
use App\Support\Kapso\KapsoClient;
use App\Support\Kapso\OptOutRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

#[Signature('leads:follow-up')]
#[Description('Send the receptionist\'s second message to clients nobody has answered yet.')]
class FollowUpWaitingLeads extends Command
{
    /**
     * The second message, sent on a clock instead of waiting to be earned.
     *
     * Until now it only arrived if the client wrote again, so somebody who
     * asked one question and then went quiet got a greeting and nothing else —
     * no chance to send her photos, no link, and a silence that reads as being
     * ignored. Ten minutes later this leans back in.
     *
     * **Four things have to be true before a word is sent**, and each one has
     * been a real failure somewhere in this project:
     *
     * 1. The professional has not answered by hand. Checked against Kapso, not
     *    guessed — a timer that talks over her is exactly the complaint that
     *    started the whole receptionist idea.
     * 2. The client has not asked to be left alone. STOP is now printed in the
     *    first message, so it will be used.
     * 3. The conversation has not already had its two messages.
     * 4. She has not been booked in the meantime, which closes the card.
     *
     * The count is written **before** the send, not after: a Kapso failure
     * costing one client her second message is a much smaller harm than a
     * failure that half-sent and then retried into her phone every minute.
     */
    public function handle(KapsoClient $kapso, Receptionist $receptionist, OptOutRegistry $optOuts): int
    {
        $minutes = (int) (config('services.assistant.receptionist_follow_up_minutes') ?: 10);
        $cutoff = now()->subMinutes($minutes);

        $providers = Provider::query()
            ->where('bot_mode', Provider::BOT_RECEPTIONIST)
            ->whereNotNull('whatsapp_phone_number_id')
            ->get();

        $sent = 0;

        foreach ($providers as $provider) {
            $waiting = $provider->leads()->waiting()
                ->where('bot_messages_sent', 1)
                ->whereNull('answered_at')
                ->whereNotNull('conversation_id')
                ->where('last_bot_message_at', '<=', $cutoff)
                ->get();

            foreach ($waiting as $lead) {
                if ($this->followUp($kapso, $receptionist, $optOuts, $provider, $lead)) {
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} follow-up message(s).");

        return self::SUCCESS;
    }

    private function followUp(
        KapsoClient $kapso,
        Receptionist $receptionist,
        OptOutRegistry $optOuts,
        Provider $provider,
        Lead $lead,
    ): bool {
        $phoneNumberId = (string) $provider->whatsapp_phone_number_id;

        // Asked to be left alone. Nothing else matters.
        if ($optOuts->optedOut($phoneNumberId, '1'.$lead->phone)) {
            return false;
        }

        try {
            if ($this->somebodyAnswered($kapso, $lead)) {
                $lead->forceFill([
                    'status' => Lead::STATUS_HANDLED,
                    'answered_at' => now(),
                ])->save();

                return false;
            }
        } catch (RuntimeException $exception) {
            // Unable to tell whether she has been answered. Staying quiet is
            // the safe half of that uncertainty: a missing follow-up is a
            // client who writes again, a wrong one is the assistant talking
            // over the professional in front of her client.
            Log::warning('Skipping a follow-up: could not read the conversation.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }

        // Claimed before sending: see the class docblock.
        $lead->forceFill([
            'bot_messages_sent' => $lead->bot_messages_sent + 1,
            'last_bot_message_at' => now(),
        ])->save();

        try {
            $kapso->sendText(
                phoneNumberId: $phoneNumberId,
                to: '1'.$lead->phone,
                body: $receptionist->intake($provider),
            );
        } catch (RuntimeException $exception) {
            Log::warning('A follow-up could not be delivered.', [
                'provider' => $provider->slug,
                'lead_id' => $lead->id,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }

        Log::info('Receptionist followed up after a silence.', [
            'provider' => $provider->slug,
            'lead_id' => $lead->id,
        ]);

        return true;
    }

    /**
     * Whether anybody from the salon has written in this thread since the
     * greeting went out.
     *
     * The signal is the one the assistant has always used: a message typed on
     * the salon's own phone comes back with a `from`, one this app sent comes
     * back without.
     */
    private function somebodyAnswered(KapsoClient $kapso, Lead $lead): bool
    {
        $since = $lead->last_bot_message_at?->getTimestamp() ?? 0;

        foreach ($kapso->recentMessages((string) $lead->conversation_id, 20) as $turn) {
            if ($turn['direction'] === 'outbound' && $turn['from'] !== '' && $turn['at'] >= $since) {
                return true;
            }
        }

        return false;
    }
}
