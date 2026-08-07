<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A charted match with shot-by-shot data. ULTRA tier only.
 *
 * Rally construction is the layer below the tape: the tape says what the
 * score became after each point, this says how the point was played. It has
 * its OWN id space (`rally_match_id`) — the charted corpus reaches back
 * decades and concentrates on the biggest events, so most charted matches
 * predate our own collection. `match_id` is OUR match id when the charted
 * match is also one we hold; null otherwise.
 *
 * On the single-match endpoints, `rally` holds the points in play order
 * (paged with limit/offset; `meta.total` is the match's full point count).
 * On the list endpoint `rally` is empty.
 */
final class RallyMatch extends Model
{
    /** The id this product is keyed on. */
    public ?int $rally_match_id = null;

    public ?string $source_id = null;

    /** OUR match id, when the charted match is also one we hold. Null otherwise. */
    public ?int $match_id = null;

    /** ISO 8601 date string exactly as received. */
    public ?string $date = null;

    public ?string $tournament = null;
    public ?string $round = null;
    public ?string $surface = null;

    /** M|W|null. */
    public ?string $gender = null;

    public ?int $best_of = null;

    /**
     * Up to two `{name, hand}` entries — hand may be R|L|U|A|null.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $players = null;

    /** Charted points in this match. */
    public ?int $points = null;

    public ?int $points_parsed = null;

    /** @var array<int, RallyPoint> Single-match endpoints only, play order. */
    public array $rally = [];

    /** Pagination meta of the `rally` page (single-match endpoints only). */
    public ?ListMeta $meta = null;

    protected function hydrate(array $data): void
    {
        if (isset($data['rally']) && is_array($data['rally'])) {
            $this->rally = array_values(array_filter(array_map(
                static fn ($p): ?RallyPoint => is_array($p) ? RallyPoint::fromArray($p) : null,
                $data['rally'],
            )));
        }

        $this->meta = isset($data['meta']) && is_array($data['meta'])
            ? ListMeta::fromArray($data['meta'])
            : null;
    }
}
