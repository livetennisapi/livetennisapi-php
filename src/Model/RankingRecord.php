<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One ranking record in force at the requested instant.
 *
 * `system` is always explicit and the systems are never collapsed into a
 * single "rank" — they are not comparable. ATP/WTA and the ITF circuits
 * populate `rank`+`points`; UTR populates `rating` and leaves rank/points
 * null, because UTR is a rating and has no rank.
 *
 * Listing rows (PRO mode) carry `player_name` as the ranking publisher
 * printed it, with a null `player_id` for players outside our roster — the
 * table has no silent holes. Per-player records (ULTRA mode) omit
 * `player_name`.
 */
final class RankingRecord extends Model
{
    public ?int $player_id = null;

    /** Listing rows only — the name as published; may exist with null player_id. */
    public ?string $player_name = null;

    /** atp|wta|itf_jt|itf_mt|itf_wt|utr. */
    public ?string $system = null;

    public ?string $tour = null;

    /** Null for UTR. */
    public ?int $rank = null;

    /** Null for UTR. */
    public ?int $points = null;

    /**
     * The rank at the immediately preceding snapshot week (ATP/WTA only;
     * null when no prior week is held, and always null for ITF/UTR).
     */
    public ?int $previous_rank = null;

    /** The circuit's own signed weekly movement (ITF systems only; null elsewhere). */
    public ?int $rank_movement = null;

    /** UTR only; null elsewhere. */
    public ?float $rating = null;

    /** The publication week this record took effect (ISO 8601 date string). */
    public ?string $effective_date = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $observed_at = null;
}
