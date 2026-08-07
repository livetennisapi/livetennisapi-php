<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One deep-archive result (1968–2022). BASIC tier and above (or any History
 * plan).
 *
 * Winner/loser-shaped — results data is recorded that way at the source, so
 * the winner is a FIELD, never an inference. Its own id space, separate from
 * `/matches`; `source_id` is the stable corpus key. The archive ends where
 * our own point-by-point coverage begins (2023-01), so no match is ever
 * served from two datasets.
 *
 * `event_date` is the TOURNAMENT START date — per-match dates do not exist
 * in this era's records.
 */
final class ArchiveMatch extends Model
{
    public ?int $id = null;
    public ?string $source_id = null;

    /** `atp` or `wta`. */
    public ?string $tour = null;

    /** Source tier code: G, M, A, F, D, C, O, or a futures category code. */
    public ?string $level = null;

    public ?string $tournament = null;
    public ?string $surface = null;
    public ?int $draw_size = null;

    /** Tournament START date (ISO 8601 date string exactly as received). */
    public ?string $event_date = null;

    public ?string $round = null;
    public ?int $best_of = null;
    public ?int $minutes = null;

    public ?ArchivePlayer $winner = null;
    public ?ArchivePlayer $loser = null;

    /** The final score as published, e.g. "6-4 7-6(5)", "6-3 RET", "W/O". */
    public ?string $score = null;

    /**
     * `completed`, `retired`, `walkover`, `default`, `abandoned` — parsed
     * from the score's own vocabulary; null when unparseable, never guessed.
     */
    public ?string $outcome = null;

    /**
     * Detail endpoint only. `{winner: {...}, loser: {...}}` with per-match
     * serve statistics where the source recorded them; null otherwise (most
     * rows before 1991) — never synthesised.
     *
     * @var array<string, mixed>|null
     */
    public ?array $stats = null;

    protected function hydrate(array $data): void
    {
        $this->winner = isset($data['winner']) && is_array($data['winner'])
            ? ArchivePlayer::fromArray($data['winner'])
            : null;

        $this->loser = isset($data['loser']) && is_array($data['loser'])
            ? ArchivePlayer::fromArray($data['loser'])
            : null;
    }
}
