<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A match event (break, set_won, game_won, momentum_run). PRO tier and above.
 */
final class Event extends Model
{
    public ?string $type = null;

    /** 1, 2, or null. */
    public ?int $player = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $timestamp = null;
}
