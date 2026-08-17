<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Provider;
use App\Notifications\LeadsWaiting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leads:alert')]
#[Description('Email each professional the WhatsApp requests nobody has answered yet.')]
class AlertWaitingLeads extends Command
{
    /**
     * Execute the console command.
     *
     * Receptionist mode hands every conversation to a person, so a person
     * forgetting is the one failure this design introduces that the old agent
     * did not have. This is that failure made visible.
     *
     * Each request is alerted about once — `alerted_at` is stamped whether or
     * not the mail lands, because an hourly job that re-reads the same rows
     * would mail the same list until she opens the app, and a professional who
     * learns to ignore these has no alert at all.
     *
     * Providers still in agent mode are skipped: their assistant books clients
     * itself, so nothing is waiting on them.
     */
    public function handle(): int
    {
        $hours = (int) (config('services.assistant.lead_alert_hours') ?: 3);
        $cutoff = now()->subHours($hours);

        $providers = Provider::query()
            ->where('bot_mode', Provider::BOT_RECEPTIONIST)
            ->with('user')
            ->get();

        $alerted = 0;

        foreach ($providers as $provider) {
            $waiting = $provider->leads()->waiting()
                ->whereNull('alerted_at')
                ->where('first_contact_at', '<=', $cutoff)
                ->orderBy('first_contact_at')
                ->get();

            if ($waiting->isEmpty()) {
                continue;
            }

            Lead::query()->whereIn('id', $waiting->pluck('id'))->update([
                'alerted_at' => now(),
                'updated_at' => now(),
            ]);

            $provider->user?->notify(new LeadsWaiting($waiting, $hours));

            $alerted += $waiting->count();
        }

        $this->info("Alerted about {$alerted} waiting request(s).");

        return self::SUCCESS;
    }
}
