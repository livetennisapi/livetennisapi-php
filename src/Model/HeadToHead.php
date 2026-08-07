<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * The record between two players across both halves of the product: the
 * results archive (1968–2022), where the winner is a stored column, and our
 * own completed matches (2023→now), where the winner is derived from the
 * final recorded state. BASIC tier and above (or any History plan).
 *
 * Names are the keys — archive people have no roster ids. A name fragment
 * matching more than one player is refused with a 400 `ambiguous_name`
 * listing the candidates, because two people summed into one record is a
 * wrong answer, not a convenience.
 *
 * `totals` count meetings with a KNOWN winner; `totals.undecided` counts the
 * rest. Walkovers and retirements are part of the record — each meeting
 * carries `outcome` so you can exclude them.
 *
 * On ULTRA, a per-player `stats` block adds serve/return/break-point
 * aggregates over the pairing (`archive_serve` from 1991, `current` from
 * 2023) — kept as a plain array because its shape is rich and evolving.
 */
final class HeadToHead extends Model
{
    /**
     * The resolved names (`{p1: {name}, p2: {name}}`); null when no player
     * matches the fragments.
     *
     * @var array<string, mixed>|null
     */
    public ?array $players = null;

    /**
     * `{p1_wins, p2_wins, meetings, undecided}` — wins count decided
     * meetings only.
     *
     * @var array<string, mixed>|null
     */
    public ?array $totals = null;

    /**
     * Per-surface win split; keys are surface names plus `unknown`.
     *
     * @var array<string, mixed>|null
     */
    public ?array $by_surface = null;

    /** @var array<int, H2HMeeting> Newest first, capped at 200. */
    public array $meetings = [];

    /**
     * ULTRA only — per-player aggregate stats over the pairing.
     *
     * @var array<string, mixed>|null
     */
    public ?array $stats = null;

    protected function hydrate(array $data): void
    {
        if (isset($data['meetings']) && is_array($data['meetings'])) {
            $this->meetings = array_values(array_filter(array_map(
                static fn ($m): ?H2HMeeting => is_array($m) ? H2HMeeting::fromArray($m) : null,
                $data['meetings'],
            )));
        }
    }
}
