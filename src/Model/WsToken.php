<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A connection token for the high-fan-out push feed. ULTRA tier only.
 *
 * `ws_url` is where to connect and `channels` is the channel vocabulary —
 * `match:{match_id}` per-match streams and `slate:all` for every live score
 * frame. Frames are the same allowlist score objects the polling endpoints
 * return, nested under `score`. Since 2026-10-23 every `score` frame also
 * carries `model` (`pending`, `ready` or `off`) and a state leaves the score
 * channels twice, pending then ready, with the same `score.sequence`: dedup
 * on `(match_id, score.sequence, model)`, and read a frame with no `model`
 * as `ready`. The token is short-lived: mint a fresh one on reconnect rather
 * than caching it.
 */
final class WsToken extends Model
{
    public ?string $token = null;

    /** Seconds until the token expires. */
    public ?int $expires_in = null;

    public ?string $ws_url = null;

    /**
     * Channel vocabulary, e.g. `{match: "match:{match_id}", slate: "slate:all"}`.
     * Minted with the model fields off it names the score-only pair instead,
     * `match:{match_id}:score_only` and `slate:score_only`.
     *
     * @var array<string, mixed>|null
     */
    public ?array $channels = null;

    /**
     * Echo of the `model_fields` switch the token was minted with (since
     * 2026-10-23): `off` means `channels` names the score-only streams, one
     * `model: off` frame per state with the model keys left out; `on` (the
     * default) means the score channels, every state as `pending` then
     * `ready`. Null on a server that predates the switch.
     */
    public ?string $model_fields = null;
}
