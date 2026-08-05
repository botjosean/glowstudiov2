<?php

namespace App\Support\Kapso;

/**
 * Decides which numbers the assistant is allowed to answer.
 *
 * This exists because the connected WhatsApp number is a real, working business
 * line with live client conversations on it. Answering the wrong person is not a
 * recoverable mistake — the message is already on their phone, and no rollback
 * takes it back.
 *
 * **Fails closed on purpose.** The mode defaults to the allowlist, and an empty
 * or unparseable list answers *nobody*. So a typo in the environment can only
 * ever make the assistant too quiet; it can never turn into texting a salon's
 * entire client list. Answering everyone is an explicit opt-in
 * (KAPSO_REPLY_MODE=everyone), which is the last switch to flip when the
 * assistant is genuinely ready.
 */
final class ReplyPolicy
{
    private const MODE_EVERYONE = 'everyone';

    public function allows(?string $phoneDigits): bool
    {
        if ($this->mode() === self::MODE_EVERYONE) {
            return true;
        }

        return $phoneDigits !== null && in_array($phoneDigits, $this->allowlist(), true);
    }

    /**
     * A description safe to log: says how the decision was made without
     * writing anyone's phone number into the log file.
     */
    public function describe(): string
    {
        return $this->mode() === self::MODE_EVERYONE
            ? 'everyone'
            : 'allowlist of '.count($this->allowlist());
    }

    private function mode(): string
    {
        $mode = config('services.kapso.reply_mode');

        return is_string($mode) ? strtolower(trim($mode)) : '';
    }

    /**
     * @return list<string> bare digits, matching how numbers arrive from Kapso
     */
    private function allowlist(): array
    {
        $raw = config('services.kapso.test_recipients');

        if (! is_string($raw)) {
            return [];
        }

        $digits = array_map(
            static fn (string $entry): string => preg_replace('/\D/', '', $entry) ?? '',
            explode(',', $raw)
        );

        return array_values(array_filter($digits, static fn (string $entry): bool => $entry !== ''));
    }
}
