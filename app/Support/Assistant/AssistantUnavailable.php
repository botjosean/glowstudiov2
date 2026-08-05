<?php

namespace App\Support\Assistant;

use RuntimeException;

/**
 * The assistant could not produce an answer.
 *
 * Carries whether waiting would plausibly help, because the two cases need
 * opposite handling: a rate limit or a timeout deserves a retry, while a
 * malformed generation or a rejected request will fail again identically and
 * should go straight to a human hand-off. Collapsing them would either burn
 * retries on hopeless work or give up on a blip.
 */
class AssistantUnavailable extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function transient(string $message, ?int $retryAfterSeconds = null): self
    {
        return new self($message, retryable: true, retryAfterSeconds: $retryAfterSeconds);
    }

    public static function permanent(string $message): self
    {
        return new self($message, retryable: false);
    }
}
