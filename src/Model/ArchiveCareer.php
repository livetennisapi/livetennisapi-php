<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One player's whole archive career (1968–2022) in one response: W-L record
 * (overall, by surface, by level, by year), titles, and the summed
 * serve-stat block with derived ratios. BASIC tier and above (or any
 * History plan).
 *
 * Everything is a sum or a ratio of sums over rows you can fetch
 * individually — nothing is modelled. `serve.matches_with_stats` states the
 * coverage honestly: the corpus records per-match serve statistics from
 * 1991 only. The nested blocks are kept as plain arrays because their
 * shapes are wide and additive.
 */
final class ArchiveCareer extends Model
{
    /** `{name}` — the resolved player. @var array<string, mixed>|null */
    public ?array $player = null;

    /** `{first, last}` — tournament start dates. @var array<string, mixed>|null */
    public ?array $span = null;

    /**
     * `{wins, losses, titles, by_surface, by_level}`.
     *
     * @var array<string, mixed>|null
     */
    public ?array $record = null;

    /**
     * Per-year `{year, wins, losses}` rows.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $by_year = null;

    /**
     * Summed serve statistics + derived ratios; ratio fields are null where
     * the denominator is zero. `matches_with_stats` states the sample.
     *
     * @var array<string, mixed>|null
     */
    public ?array $serve = null;
}
