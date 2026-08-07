<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Career shot-level charting aggregate for one player, from the Match
 * Charting Project. ULTRA tier only.
 *
 * Serve placement (deuce/ad × wide/body/T), return depth and outcomes, net
 * and serve-and-volley conversion, clutch break/game/set-point serving and
 * returning, winners and unforced errors by wing, and rally-length and
 * shot-direction tendencies — summed over the player's charted matches.
 *
 * Every field in `families` is a raw SUM over the player's Total rows and
 * `matches_charted` states the sample. COVERAGE IS CURATED — 11,646 charted
 * matches across both tours back to the 1960s, concentrated on the majors,
 * NOT full-slate coverage.
 */
final class ChartingPlayer extends Model
{
    /** The resolved player object. @var array<string, mixed>|null */
    public ?array $player = null;

    public ?int $matches_charted = null;
    public ?string $coverage = null;

    /**
     * Per-family summed numeric columns, keyed by family name. Kept as a
     * plain array — the family set is wide and additive.
     *
     * @var array<string, mixed>|null
     */
    public ?array $families = null;
}
