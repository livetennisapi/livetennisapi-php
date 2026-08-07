<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 429 `abuse_throttled` — a 24-hour block applied to clients that
 * chronically run far over their cap.
 *
 * This is NOT the ordinary rate-limit window: waiting a few seconds will
 * not clear it, so the client never auto-retries it. `getRetryAtEpoch()`
 * is the Unix timestamp when the block lifts. If you see this, fix the
 * retry loop that is hammering the API (back off on 429 instead of
 * retrying immediately) rather than working around the block.
 */
class AbuseThrottled extends RateLimited
{
    /** Unix epoch seconds when the block lifts, from the error body. */
    public function getRetryAtEpoch(): ?int
    {
        $v = $this->bodyField('retry_at_epoch');

        return is_int($v) ? $v : null;
    }
}
