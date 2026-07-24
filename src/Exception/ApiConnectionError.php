<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * The request never produced a response (DNS, TLS, connection refused, dropped).
 */
class ApiConnectionError extends LiveTennisApiError
{
}
