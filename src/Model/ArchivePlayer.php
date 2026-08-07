<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One side of an archive result — the player as the corpus recorded them AT
 * THE TIME of the match.
 *
 * `player_id` is the corpus person id (joins `/history/archive/players`
 * within the same tour) — NOT a roster player id; the archive is a separate
 * id space keyed on names. `rank` is the rank at the time as published, not
 * today's. Null fields are the era's silence, never guessed.
 */
final class ArchivePlayer extends Model
{
    public ?string $name = null;
    public ?string $hand = null;

    /** 3-letter code, same vocabulary as `Player.country`. */
    public ?string $country = null;

    /** The player's rank AT THE TIME of the match, as published. */
    public ?int $rank = null;

    public ?int $seed = null;

    /** Corpus person id — joins /history/archive/players. NOT a roster id. */
    public ?int $player_id = null;

    public ?int $height_cm = null;

    /** Age at the time of the match, as the corpus records it. */
    public ?float $age = null;

    /** Draw entry where recorded (WC, Q, LL, PR, SE, …) — null for direct acceptances. */
    public ?string $entry = null;
}
