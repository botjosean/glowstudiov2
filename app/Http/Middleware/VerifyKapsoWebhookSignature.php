<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any webhook delivery not signed with the shared secret.
 *
 * Runs before the controller and before anything reads the payload: an
 * unverified body is attacker-controlled input and must not reach the parser,
 * the queue, or the database.
 */
class VerifyKapsoWebhookSignature
{
    private const SIGNATURE_HEADER = 'X-Webhook-Signature';

    /**
     * Kapso's own documentation contradicts itself about what gets signed: the
     * prose says "the raw JSON payload" while every code sample re-serialises
     * the parsed body (`JSON.stringify(payload)` / `json.dumps(payload)`).
     * Rather than guess, both candidates are accepted — each is an HMAC under
     * the same secret, so accepting either forges nothing, and the match is
     * logged so the ambiguity can be closed with evidence instead of a reading
     * of the docs.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.kapso.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            // A missing secret must never degrade into "accept everything".
            Log::error('Kapso webhook secret is not configured; rejecting delivery.');

            abort(500);
        }

        $provided = $this->providedSignature($request);

        if ($provided === null) {
            abort(401);
        }

        $variant = $this->matchingVariant($request, $secret, $provided);

        if ($variant === null) {
            Log::warning('Kapso webhook signature mismatch.', [
                'idempotency_key' => $request->header('X-Idempotency-Key'),
                'event' => $request->header('X-Webhook-Event'),
            ]);

            abort(401);
        }

        Log::info('Kapso webhook signature verified.', ['signed_body' => $variant]);

        return $next($request);
    }

    /**
     * v2 sends a bare hex digest. An older documented example used Meta's
     * `sha256=` prefix, so it is tolerated on the way in rather than causing a
     * spurious 401 if Kapso ever reverts.
     */
    private function providedSignature(Request $request): ?string
    {
        $header = $request->header(self::SIGNATURE_HEADER);

        if (! is_string($header)) {
            return null;
        }

        $header = trim($header);

        if (str_starts_with($header, 'sha256=')) {
            $header = substr($header, 7);
        }

        return $header === '' ? null : $header;
    }

    /**
     * @return 'raw'|'reserialized'|null the body representation that matched
     */
    private function matchingVariant(Request $request, string $secret, string $provided): ?string
    {
        $raw = $request->getContent();

        if ($this->matches($raw, $secret, $provided)) {
            return 'raw';
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            // JSON_UNESCAPED_* mirrors JavaScript's JSON.stringify, which is
            // what the documented samples use; PHP's defaults would escape
            // slashes and non-ASCII and never match.
            $reserialized = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($reserialized) && $this->matches($reserialized, $secret, $provided)) {
                return 'reserialized';
            }
        }

        return null;
    }

    private function matches(string $body, string $secret, string $provided): bool
    {
        return hash_equals(hash_hmac('sha256', $body, $secret), $provided);
    }
}
