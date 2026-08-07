<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Your own usage vs quota. Any tier, and QUOTA-EXEMPT — polling it does not
 * consume your daily budget.
 *
 * Durable daily usage for the calling key: tier, limits, today's calls
 * (current to the second) and a 30-day history. The per-minute window lives
 * on the `X-RateLimit-*` headers of every response, not here — and the
 * daily reset instant is only surfaced on the daily-429 body
 * (`RateLimited::getResetsAt()`), not on this object.
 */
final class Usage extends Model
{
    /** Opaque reference to your own key. */
    public ?string $principal = null;

    /** free|basic|pro|ultra — the tier currently in force. */
    public ?string $tier = null;

    /** Subscription tier; equals `tier` unless a temporary grant is active. */
    public ?string $base_tier = null;

    /** When a temporary tier grant reverts (ISO 8601 UTC), else null. */
    public ?string $tier_expires_at = null;

    public ?string $channel = null;

    /** `{per_minute, per_day}` — either may be null. @var array<string, mixed>|null */
    public ?array $limits = null;

    /** `{calls, errors, remaining_day}`. @var array<string, mixed>|null */
    public ?array $today = null;

    /**
     * Last 30 days, oldest first: `{day, calls, errors}` rows.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $history = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $as_of = null;
}
