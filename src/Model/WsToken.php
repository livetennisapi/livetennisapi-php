<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A connection token for the high-fan-out push feed. ULTRA tier only.
 *
 * `ws_url` is where to connect and `channels` is the channel vocabulary —
 * `match:{match_id}` per-match streams and `slate:all` for every live score
 * frame. Frames are the same allowlist score objects the polling endpoints
 * return. The token is short-lived: mint a fresh one on reconnect rather
 * than caching it.
 */
final class WsToken extends Model
{
    public ?string $token = null;

    /** Seconds until the token expires. */
    public ?int $expires_in = null;

    public ?string $ws_url = null;

    /**
     * Channel vocabulary, e.g. `{match: "match:{id}", slate: "slate:all"}`.
     *
     * @var array<string, mixed>|null
     */
    public ?array $channels = null;
}
