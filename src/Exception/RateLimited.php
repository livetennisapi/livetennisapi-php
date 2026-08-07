<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 429 — the tier's rate-limit window was exceeded.
 *
 * `retryAfter` is the number of seconds the API asked you to wait, parsed from
 * the `Retry-After` header. It is `null` when the header is absent or
 * unparseable.
 *
 * The API sends two `rate_limited` shapes, distinguishable by scope:
 *
 *  - the per-MINUTE window — `getScope()` is null; wait `getRetryAfter()`
 *    seconds and continue;
 *  - the per-DAY quota — `getScope()` is `"day"`, `getLimitPerDay()` carries
 *    the tier's daily limit and `getResetsAt()` the absolute ISO 8601
 *    instant the quota resets (derived from the service's local midnight,
 *    deliberately not a fixed UTC hour — always parse the instant).
 *
 * A third 429, the abuse block, is its own type: {@see AbuseThrottled}.
 */
class RateLimited extends ApiStatusError
{
    /**
     * @param mixed                 $body
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        int $statusCode,
        mixed $body = null,
        array $headers = [],
        ?string $requestUrl = null,
        private readonly ?float $retryAfter = null,
    ) {
        if ($retryAfter !== null) {
            $message = "{$message} — retry after " . rtrim(rtrim(sprintf('%.3f', $retryAfter), '0'), '.') . 's';
        }

        parent::__construct($message, $statusCode, $body, $headers, $requestUrl);
    }

    public function getRetryAfter(): ?float
    {
        return $this->retryAfter;
    }

    /** `"day"` on a daily-quota 429; null on the per-minute window. */
    public function getScope(): ?string
    {
        $v = $this->bodyField('scope');

        return is_string($v) ? $v : null;
    }

    /** Whether this is the daily quota (vs the per-minute window). */
    public function isDaily(): bool
    {
        return $this->getScope() === 'day';
    }

    /**
     * Absolute ISO 8601 instant the daily quota resets, from the daily-429
     * body. Null on the per-minute window. Derived from the service's local
     * midnight — not a fixed UTC hour, so parse the instant rather than
     * assuming one.
     */
    public function getResetsAt(): ?string
    {
        $v = $this->bodyField('resets_at');

        return is_string($v) ? $v : null;
    }

    /** The tier's daily limit, from the daily-429 body. */
    public function getLimitPerDay(): ?int
    {
        $v = $this->bodyField('limit_per_day');

        return is_int($v) ? $v : null;
    }

    protected function bodyField(string $key): mixed
    {
        $body = $this->getBody();

        return is_array($body) ? ($body[$key] ?? null) : null;
    }
}
