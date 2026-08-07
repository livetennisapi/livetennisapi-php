<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Per-match tape: match header + chronological point-by-point score
 * sequence + model profiles + coverage meta. BASIC tier and above (or any
 * History plan).
 *
 * The tape is NOT guaranteed to cover the whole match — check
 * `meta['coverage']` and `meta['point_source']` before backtesting.
 * WORKS ON A LIVE MATCH, not only a completed one: the tape is assembled
 * from whatever has been committed so far.
 *
 * `meta` keys: `match_id`, `rows` (returned, after any clean collapse),
 * `coverage` (`from_start` | `partial` | `reconstructed` |
 * `reconstructed_partial` | `none`), `point_source` (`observed` |
 * `reconstructed` | `mixed` | null), `raw_rows`, `unique_states`,
 * `sequence`, `from_archive`, `generated_at`.
 */
final class HistoryTape extends Model
{
    /** The match header. */
    public ?TennisMatch $match = null;

    /** @var array<int, HistoryTapeRow> Chronological score sequence. */
    public array $tape = [];

    /**
     * Per-set tiebreak final scores from OBSERVED states only, aligned to
     * the sets of the final scoreline: `{p1, p2}` for a 7-6 set whose
     * observed maximum tiebreak state is a valid terminal shape, null per
     * set otherwise — a breaker whose closing point the feed skipped reads
     * null rather than an under-report. Null when the match has no 7-6 set.
     *
     * @var array<int, array<string, int>|null>|null
     */
    public ?array $tiebreaks = null;

    /**
     * Model profiles, oldest first (the Analysis `profile` shape).
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $profiles = null;

    /**
     * Coverage meta — see the class docblock for the keys.
     *
     * @var array<string, mixed>|null
     */
    public ?array $meta = null;

    protected function hydrate(array $data): void
    {
        $this->match = isset($data['match']) && is_array($data['match'])
            ? TennisMatch::fromArray($data['match'])
            : null;

        if (isset($data['tape']) && is_array($data['tape'])) {
            $this->tape = array_values(array_filter(array_map(
                static fn ($row): ?HistoryTapeRow => is_array($row) ? HistoryTapeRow::fromArray($row) : null,
                $data['tape'],
            )));
        }
    }
}
