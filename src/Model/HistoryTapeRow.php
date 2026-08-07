<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One row of a match's point-by-point score sequence.
 *
 * Rows we watched live carry a real `timestamp`. Rows expanded after the
 * fact from a finished-match point-by-point record carry a null `timestamp`
 * AND null model fields, because neither a wall clock nor a model output
 * ever existed for them — nothing is synthesised. A null `timestamp` is the
 * reliable row-level marker of a reconstructed row; the model fields alone
 * are not, since they are stamped best-effort and an observed row may lack
 * them. Null model fields mean "the model had no output", not "not
 * entitled".
 *
 * Same score conventions as {@see Score}: `points` are strings, `games` is
 * player-major per-set lists, `server` is 1|2|null.
 */
final class HistoryTapeRow extends Model
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

    public ?float $win_probability_p1 = null;
    public ?float $danger = null;

    /** ISO 8601 UTC string, or null on a reconstructed row. */
    public ?string $timestamp = null;

    /**
     * Who won the point this row records — PRESENT ONLY on
     * `?sequence=clean` rows, and only where the transition from the
     * previous row is a single attributable point; null on gaps, torn rows
     * and the first row. Never on the raw sequence (raw is deliberately
     * non-monotonic: consecutive raw rows are corrections, not points).
     * Derived at read time, never stored or guessed.
     */
    public ?int $point_winner = null;
}
