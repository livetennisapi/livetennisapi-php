<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 400 — a query parameter was malformed (e.g. an unrecognised `tour` value).
 */
class BadRequest extends ApiStatusError
{
}
