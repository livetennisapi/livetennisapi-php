<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One head-to-head meeting.
 *
 * `era` says which half of the product served the row: `archive` rows carry
 * `archive_match_id`/`level`/`score`; `current` rows carry
 * `match_id`/`round_code` and read their score from the match endpoints
 * (fetch the row's `match_id` there).
 *
 * `winner` is 1|2 OF THIS H2H — p1/p2 as requested, not as the source
 * stored them — and null when underivable.
 */
final class H2HMeeting extends Model
{
    /** `archive` or `current`. */
    public ?string $era = null;

    /** ISO 8601 date string exactly as received. */
    public ?string $date = null;

    public ?string $tournament = null;
    public ?string $level = null;
    public ?string $round = null;
    public ?string $surface = null;

    /** Archive rows only — the final score as published, e.g. "6-4 7-6(5)". */
    public ?string $score = null;

    /** `completed`, `retired`, `walkover`, … — null when unparseable. */
    public ?string $outcome = null;

    /** 1|2 of this H2H's p1/p2, or null when underivable. */
    public ?int $winner = null;
}
