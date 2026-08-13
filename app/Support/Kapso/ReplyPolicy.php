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
 *
 * **One extra guard, only for a number still shared with personal use.** A
 * professional migrating a personal WhatsApp number into the business (see
 * KAPSO_PERSONAL_PHONE_NUMBER_ID) still has her own contacts saved on that
 * phone. Kapso echoes the sender's saved contact name when one exists — but
 * *not* as null when there is none: it falls back to the bare phone number
 * as the "name" instead, confirmed against real conversations on Patricia's
 * number. So the signal is not "a name is present", it is "the name is not
 * just the sender's own digits". Temporary, needs no list to maintain, and
 * stops applying the day she gives the number to the business alone (unset
 * the id and this guard is inert).
 */
final class ReplyPolicy
{
    private const MODE_EVERYONE = 'everyone';

    /**
     * @param  string  $phoneNumberId  the business number the message arrived on
     * @param  ?string  $contactName  the sender's name as Kapso reports it —
     *                                present only when saved as a contact on that phone
     */
    public function allows(?string $phoneDigits, string $phoneNumberId, ?string $contactName): bool
    {
        if ($this->isSavedContactOnAGuardedNumber($phoneDigits, $phoneNumberId, $contactName)) {
            return false;
        }

        if ($this->mode() === self::MODE_EVERYONE) {
            return true;
        }

        return $phoneDigits !== null && in_array($phoneDigits, $this->allowlist(), true);
    }

    /**
     * Why a refused message was refused, in a form safe to log.
     *
     * The count alone could not answer the question that actually gets asked
     * of it. On 2026-08-13 more than twenty messages in one day were dropped
     * on the number shared with personal use, and the log could not say
     * whether that was the guard doing its job on the professional's own
     * contacts or eating clients she had saved after their first visit —
     * which is a decision for the owner, and needs the reason to be reachable
     * without reading anybody's conversations.
     */
    public function refusalReason(?string $phoneDigits, string $phoneNumberId, ?string $contactName): string
    {
        return $this->personalContactVerdict($phoneDigits, $phoneNumberId, $contactName) ?? 'not in the allowlist';
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

    private function isSavedContactOnAGuardedNumber(?string $phoneDigits, string $phoneNumberId, ?string $contactName): bool
    {
        return $this->personalContactVerdict($phoneDigits, $phoneNumberId, $contactName) !== null;
    }

    /**
     * Why the personal-contact guard silenced this message, or null when it
     * did not apply.
     *
     * The three answers are deliberately separate, because they are not the
     * same event and only one of them is the guard working. The whole guard
     * rests on an assumption about a shape Kapso does not document: that when
     * nothing is saved it echoes the sender's own number back as the "name".
     * If that shape ever changes — a `+`, a country code dropped, a space —
     * every stranger reads as a saved contact and the assistant goes silent
     * for the entire number without a single error anywhere. That is
     * indistinguishable from the guard working correctly unless the log says
     * which branch fired, and on 2026-08-13 twenty-odd dropped messages in one
     * day left exactly that question unanswerable.
     */
    private function personalContactVerdict(?string $phoneDigits, string $phoneNumberId, ?string $contactName): ?string
    {
        $guarded = config('services.kapso.personal_phone_number_id');

        if (! is_string($guarded) || trim($guarded) === '' || trim($guarded) !== $phoneNumberId) {
            return null;
        }

        if ($contactName === null || trim($contactName) === '') {
            return null;
        }

        // Kapso's fallback when nothing is saved is the bare phone number
        // itself, so a "name" that reduces to those same digits is no name
        // at all — only something else means a real contact exists.
        $nameDigits = preg_replace('/\D/', '', $contactName) ?? '';

        if ($nameDigits === '') {
            return 'saved under a name on the number shared with personal use';
        }

        if ($phoneDigits === null) {
            return 'guarded number and no sender phone to compare the name against';
        }

        if ($nameDigits !== $phoneDigits) {
            // A name that carries digits — or the fallback arriving in a shape
            // this comparison does not expect. Worth telling apart in the log,
            // because the second one silences everybody.
            return 'name with digits that do not match the sender on the guarded number';
        }

        return null;
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
