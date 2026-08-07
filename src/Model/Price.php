<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One price tick. `side` is 1 for p1's outcome, 2 for p2's.
 *
 * A prediction-market top-of-book quote (probability-like, in [0,1]); it
 * reflects market trading, not an official line, and can lag live scores.
 */
final class Price extends Model
{
    public ?int $side = null;
    public ?float $bid = null;
    public ?float $ask = null;
    public ?float $mid = null;
    public ?float $spread = null;

    /** Feed category, e.g. `prediction_market`. */
    public ?string $price_source = null;

    /**
     * true = bid/ask estimated from mid (not a live order book); false =
     * real top-of-book; null = unknown (older ticks). Tagged so a
     * synthesised quote is never mistaken for a live book.
     */
    public ?bool $synthetic = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $timestamp = null;
}
