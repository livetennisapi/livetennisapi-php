<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 401 — the key is missing, unknown, or disabled.
 */
class Unauthorized extends ApiStatusError
{
}
