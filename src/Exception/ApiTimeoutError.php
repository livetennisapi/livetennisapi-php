<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * The request exceeded the configured timeout.
 */
class ApiTimeoutError extends ApiConnectionError
{
}
