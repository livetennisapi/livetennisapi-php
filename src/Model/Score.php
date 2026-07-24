<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A match score at a point in time.
 *
 * `sets` is `[sets_p1, sets_p2]`.
 *
 * `games` is `[games_p1, games_p2]` where **each side is a per-set list** — so
 * `[[6, 3, 2], [4, 6, 1]]` reads 6-4, 3-6, 2-1. Note this is player-major, not
 * set-major; indexing it the other way is the single most common mistake
 * against this API. The sub-arrays grow by one entry per set played.
 *
 * `points` are STRINGS ("0", "15", "30", "40", "AD") — never integers.
 *
 * `server` is 1, 2, or **null** (unknown / between points).
 *
 * `win_probability_p1` and `danger` are present only on the ULTRA tier.
 */
final class Score extends Model
{
    /** @var array<int, int>|null [sets_p1, sets_p2] */
    public ?array $sets = null;

    /** @var array<int, array<int, int>>|null [games_p1[], games_p2[]] — player-major */
    public ?array $games = null;

    /** @var array<int, string>|null Point values as strings, e.g. ["40", "AD"] */
    public ?array $points = null;

    /** 1, 2, or null. */
    public ?int $server = null;

    public ?bool $is_tiebreak = null;

    /** ULTRA only. */
    public ?float $win_probability_p1 = null;

    /** ULTRA only. */
    public ?float $danger = null;

    /** ISO 8601 UTC string exactly as received (never coerced). */
    public ?string $timestamp = null;

    /**
     * Games for one set as `[p1, p2]`, guarding the player-major layout.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function gamesForSet(int $setIndex): array
    {
        if ($this->games === null || count($this->games) < 2) {
            return [null, null];
        }

        $p1 = $this->games[0] ?? [];
        $p2 = $this->games[1] ?? [];

        return [
            is_array($p1) ? ($p1[$setIndex] ?? null) : null,
            is_array($p2) ? ($p2[$setIndex] ?? null) : null,
        ];
    }
}
