<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * Maps HTTP status codes and request paths onto the exception hierarchy.
 *
 * Kept in one place so the sync client (and any future async variant) share a
 * single source of truth for error semantics — matching the Python `_base` and
 * JS `errorForStatus` helpers.
 */
final class ErrorFactory
{
    /**
     * Endpoints that need more than the FREE floor, so a 403 can say which tier
     * is needed rather than surfacing the API's bare `{"error":"upgrade_required"}`.
     * First matching marker wins.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const TIER_REQUIREMENTS = [
        ['/analysis', 'ULTRA'],
        ['/statistics', 'ULTRA'],
        ['/rally', 'ULTRA'],
        ['/charting', 'ULTRA'],
        ['/ws-token', 'ULTRA'],
        ['/webhooks', 'ULTRA'],
        ['/events', 'PRO'],
        ['/markets', 'PRO'],
        ['/prices', 'PRO'],
        // Listing mode (no ?player=) is PRO; per-player as-of mode is ULTRA.
        // Path-based inference can only state the floor.
        ['/rankings', 'PRO'],
        ['/history/packages', 'PRO'],
        ['/h2h', 'BASIC'],
        ['/history', 'BASIC'],
    ];

    /**
     * The lowest tier that unlocks an endpoint, or null for a FREE endpoint.
     */
    public static function requiredTierFor(string $path): ?string
    {
        foreach (self::TIER_REQUIREMENTS as [$marker, $tier]) {
            if (str_contains($path, $marker)) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Parse the `Retry-After` header. Only the delta-seconds form is emitted by
     * the API.
     *
     * @param array<string, string> $headers
     */
    public static function retryAfterSeconds(array $headers): ?float
    {
        $raw = null;
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'retry-after') === 0) {
                $raw = $value;
                break;
            }
        }

        if ($raw === null || trim($raw) === '' || !is_numeric(trim($raw))) {
            return null;
        }

        $value = (float) trim($raw);

        return $value >= 0 ? $value : null;
    }

    /**
     * Build the right exception for a non-2xx response.
     *
     * @param mixed                 $body
     * @param array<string, string> $headers
     */
    public static function make(
        int $status,
        string $message,
        string $path,
        mixed $body = null,
        array $headers = [],
        ?string $requestUrl = null,
    ): ApiStatusError {
        return match (true) {
            $status === 400 => new BadRequest($message, $status, $body, $headers, $requestUrl),
            $status === 401 => new Unauthorized($message, $status, $body, $headers, $requestUrl),
            $status === 403 => new UpgradeRequired(
                $message,
                $status,
                $body,
                $headers,
                $requestUrl,
                self::requiredTierFor($path),
            ),
            $status === 404 => new NotFound($message, $status, $body, $headers, $requestUrl),
            $status === 409 => new Conflict($message, $status, $body, $headers, $requestUrl),
            $status === 429 && self::isAbuseThrottled($body) => new AbuseThrottled(
                $message,
                $status,
                $body,
                $headers,
                $requestUrl,
                self::retryAfterSeconds($headers),
            ),
            $status === 429 => new RateLimited(
                $message,
                $status,
                $body,
                $headers,
                $requestUrl,
                self::retryAfterSeconds($headers),
            ),
            $status === 503 => new ServiceUnavailable($message, $status, $body, $headers, $requestUrl),
            $status >= 500 => new ServerError($message, $status, $body, $headers, $requestUrl),
            default => new ApiStatusError($message, $status, $body, $headers, $requestUrl),
        };
    }

    /**
     * Whether this 429 body is the abuse block (`abuse_throttled`) — a
     * 24-hour ban on chronically over-cap clients, which retrying cannot
     * clear and must not be retried.
     *
     * @param mixed $body
     */
    public static function isAbuseThrottled(mixed $body): bool
    {
        return is_array($body) && ($body['error'] ?? null) === 'abuse_throttled';
    }

    /**
     * Whether retrying can plausibly fix this status.
     *
     * 429 and 5xx are transient. Every other 4xx is a client-side mistake —
     * a bad key, an unentitled tier, a missing id — and retrying it just burns
     * rate-limit budget against a request that cannot start working.
     * (The one non-transient 429, `abuse_throttled`, is excluded by body in
     * the request loop via {@see isAbuseThrottled()}.)
     */
    public static function shouldRetry(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }
}
