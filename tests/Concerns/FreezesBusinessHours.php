<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;

/**
 * These tests are about what the assistant says, not when it is allowed to
 * speak — that gate is its own, deliberately explicit test (see "it says
 * nothing outside its hours" and its neighbours, which set the clock
 * themselves). Every other test here calls now() through that same gate on
 * the way in, for real: whatever wall-clock time happens to run the suite.
 *
 * A provider's default bot hours are 9am-10pm in her own timezone (see
 * ProviderFactory) — a normal time to be working here is not a normal time
 * for a provider in New York, so any run past ~10pm US Eastern silently
 * turned every reply into null and every test that read one red, for a
 * reason that had nothing to do with what it was actually testing.
 */
trait FreezesBusinessHours
{
    protected function freezeToBusinessHours(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-06-11 14:00:00', 'America/New_York'));
    }

    protected function unfreezeClock(): void
    {
        Carbon::setTestNow();
    }
}
