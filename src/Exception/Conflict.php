<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 409 — the request conflicts with existing state, e.g. `webhook_limit`
 * (the key already has its maximum of 3 webhooks — delete one first).
 */
class Conflict extends ApiStatusError
{
}
