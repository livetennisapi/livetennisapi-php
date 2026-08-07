<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * An outbound webhook registration. ULTRA, DIRECT keys only (RapidAPI keys
 * get a 403 `direct_key_required`); up to 3 per key.
 *
 * The API POSTs the same frames the WebSocket sends to your HTTPS endpoint
 * on every live score commit, signed with `secret`.
 *
 * `secret` is present ONLY on the registration response (201) — it is shown
 * exactly once and never returned by the list endpoint, so store it when
 * you create the webhook.
 */
final class Webhook extends Model
{
    public ?int $id = null;

    /** HTTPS only, publicly routable. */
    public ?string $url = null;

    /** Subscribed events: `score` and/or `break_point`. @var array<int, string>|null */
    public ?array $events = null;

    public ?bool $enabled = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $created_at = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $last_delivery_at = null;

    public ?int $consecutive_failures = null;
    public ?string $last_error = null;

    /** Present ONLY on the 201 registration response — shown exactly once. */
    public ?string $secret = null;

    public ?string $secret_note = null;
}
