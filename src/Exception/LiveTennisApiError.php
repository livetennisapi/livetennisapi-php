<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

use RuntimeException;

/**
 * Base class for every error raised by this library.
 *
 * Mirrors the `LiveTennisAPIError` base in the Python and JS clients so the
 * error taxonomy is identical across languages.
 */
class LiveTennisApiError extends RuntimeException
{
}
