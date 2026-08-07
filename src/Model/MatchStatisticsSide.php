<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One player's in-play statistics, in TWO families that are deliberately
 * not merged.
 *
 * The typed fields at this level are DERIVED — rebuilt from the
 * point-by-point record: service and return games played and won, hold and
 * break percentage, break points faced, saved and converted, service and
 * return points.
 *
 * `measured` holds counts taken upstream, so it includes what no point
 * record can yield — ACES AND DOUBLE FAULTS, the first- and second-serve
 * split, winners and unforced errors. Both families name some of the same
 * quantities, computed two entirely different ways; that is a cross-check,
 * not a duplication to collapse.
 *
 * Percentage fields are null when their denominator is zero — never 0, so
 * a present 0 is a real measured zero.
 */
final class MatchStatisticsSide extends Model
{
    /**
     * MEASURED family — counted upstream. EVERY field is optional and an
     * absent field is OMITTED, never zero-filled: read the keys you are
     * given. Aces and double faults are present across every tour; the
     * serve split and break points saved are absent on ITF singles; winners
     * and unforced errors appear on a minority of main-tour matches. A
     * `_of` suffix is the denominator of its base field, `_pct` the
     * recomputed percentage.
     *
     * @var array<string, mixed>|null
     */
    public ?array $measured = null;

    public ?int $service_games_played = null;
    public ?int $service_games_won = null;

    /** Null when no service game was played. */
    public ?int $hold_pct = null;

    public ?int $return_games_played = null;
    public ?int $return_games_won = null;
    public ?int $break_pct = null;
    public ?int $break_points_faced = null;
    public ?int $break_points_saved = null;
    public ?int $break_points_saved_pct = null;
    public ?int $break_points_played = null;
    public ?int $break_points_converted = null;
    public ?int $break_points_converted_pct = null;
    public ?int $service_points_played = null;
    public ?int $service_points_won = null;
    public ?int $service_points_won_pct = null;
    public ?int $return_points_played = null;
    public ?int $return_points_won = null;
    public ?int $return_points_won_pct = null;
    public ?int $points_played = null;
    public ?int $points_won = null;
}
