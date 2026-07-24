<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One price tick. `side` is 1 for p1's outcome, 2 for p2's.
 */
final class Price extends Model
{
    public ?int $side = null;
    public ?float $bid = null;
    public ?float $ask = null;
    public ?float $mid = null;
    public ?float $spread = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $timestamp = null;
}
