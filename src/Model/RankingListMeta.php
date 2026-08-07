<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Pagination meta of `/rankings`, extended with a `coverage` block saying
 * what resolved against what was asked.
 *
 * Read `coverage` before trusting an empty result: ITF and UTR history
 * begins 2026-07-29 and cannot be reconstructed earlier, so a request
 * before that date correctly returns nothing for those systems —
 * `coverage.oldest_available` gives the earliest date each requested
 * system can answer for.
 */
final class RankingListMeta extends ListMeta
{
    /**
     * `{as_of, players_requested, players_resolved, systems_requested,
     * systems_resolved, oldest_available}`.
     *
     * @var array<string, mixed>|null
     */
    public ?array $coverage = null;
}
