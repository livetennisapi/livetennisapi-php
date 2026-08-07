<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * In-play statistics for one match — aces, double faults, serve split,
 * hold/break %, break points, service & return points. ULTRA tier only.
 *
 * Branch on `freshness.derived.coverage` / `freshness.measured.coverage`
 * (`live` | `final` | `stale` | `none` | `diverged`) rather than the
 * top-level `coverage`, which only summarises the response. On `diverged`
 * the measured VALUES are withheld and `freshness.measured_divergence` says
 * why. `none` on both returns 200 with null `players`, not 404 — the match
 * exists and holding nothing for it is the honest answer.
 *
 * THE TWO AGES USE DIFFERENT CLOCKS AND MUST NOT BE COMPARED: the derived
 * age is measured against the newest SCORE row (between points there is no
 * new score either), the measured age is wall clock.
 *
 * Tiebreak games are excluded from the DERIVED family and counted in
 * `tiebreak_games_excluded` — the live record collapses a whole tiebreak
 * onto one entry, so most of its points are lost.
 */
final class MatchStatistics extends Model
{
    public ?int $match_id = null;

    /** live|final|stale|none|diverged — a summary; branch on `freshness`. */
    public ?string $coverage = null;

    /** When the underlying record was last updated (UTC), exactly as received. */
    public ?string $as_of = null;

    /** Behind the newest SCORE row, not the wall clock. */
    public ?int $age_seconds = null;

    public ?int $games_counted = null;
    public ?int $tiebreak_games_excluded = null;

    /** Games whose recorded outcome is neither a legal hold nor a legal break. */
    public ?int $inconsistent_games_excluded = null;

    /** @var array<int, int>|null */
    public ?array $sets_covered = null;

    /**
     * Per-family coverage and age: `{derived, measured, measured_divergence}`,
     * each family carrying `coverage`, `as_of`, `age_seconds` and
     * `describes` (the match state the numbers describe).
     *
     * @var array<string, mixed>|null
     */
    public ?array $freshness = null;

    /** Present only when coverage is `none`. */
    public ?string $detail = null;

    /**
     * `{p1: MatchStatisticsSide, p2: MatchStatisticsSide}`, or null when we
     * hold nothing for the match.
     *
     * @var array<string, MatchStatisticsSide>|null
     */
    public ?array $players = null;

    protected function hydrate(array $data): void
    {
        if (isset($data['players']) && is_array($data['players'])) {
            $players = [];
            foreach ($data['players'] as $key => $val) {
                if (is_array($val)) {
                    $players[$key] = MatchStatisticsSide::fromArray($val);
                }
            }
            $this->players = $players;
        }
    }

    /** Player 1's statistics, or null. */
    public function p1(): ?MatchStatisticsSide
    {
        $p = $this->players['p1'] ?? null;

        return $p instanceof MatchStatisticsSide ? $p : null;
    }

    /** Player 2's statistics, or null. */
    public function p2(): ?MatchStatisticsSide
    {
        $p = $this->players['p2'] ?? null;

        return $p instanceof MatchStatisticsSide ? $p : null;
    }
}
