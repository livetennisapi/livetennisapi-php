<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One player's record over our own completed singles matches (2023 onward),
 * from `GET /players/{id}/stats`. **PRO**; `serve_return` is **ULTRA**.
 *
 * `records` holds W/L `overall`, `last_12_months`, `by_surface`,
 * `by_venue`, `by_level` and `by_season`; the set-based record under `sets`
 * (tiebreaks, deciding sets, after winning or losing the first set,
 * comebacks, sets won and lost); `withdrawals` given and received;
 * `excluded` counts by reason; and `recent`, the last N matches newest
 * first with the score turned round to this player. A recent row's
 * `opponent` is `?array`: null when the match row holds no opponent id. A
 * percentage is null, never 0, on an empty sample.
 *
 * `serve_return` sums the measured per-match statistics over the window:
 * each ratio is `{numerator, denominator, pct, matches}`, each count
 * `{total, matches, per_match}`. The server omits it below ULTRA, so it
 * stays null there. The nested blocks are kept as plain arrays because
 * their shapes are wide and additive.
 */
final class PlayerStats extends Model
{
    /** `{id, name}`. @var array<string, mixed>|null */
    public ?array $player = null;

    /** `{from, to}` — the window applied, ISO 8601 UTC. @var array<string, mixed>|null */
    public ?array $window = null;

    /** `{surface, level, last}` — the filters applied. @var array<string, mixed>|null */
    public ?array $filters = null;

    /** The results-based record (PRO). @var array<string, mixed>|null */
    public ?array $records = null;

    /** Summed serve/return statistics (ULTRA); null below ULTRA. @var array<string, mixed>|null */
    public ?array $serve_return = null;

    /** `{generated_at, note}`. @var array<string, mixed>|null */
    public ?array $meta = null;
}
