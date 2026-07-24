<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A player, or a doubles team.
 *
 * `tour` is the record's OWN tour, which is NOT the `tour` filter vocabulary.
 * It is granular (`juniors_boys`, `challenger_men`) where the filter is grouped
 * (`juniors`, `challenger`), and a doubles team reports it UPPERCASE (`ATP`)
 * where an individual reports lowercase (`atp`). Treat it as an opaque string;
 * do not parse it into the filter enum.
 *
 * `data_completeness` is nullable as a whole (absent outside a match payload)
 * and its `known`/`of` are themselves null on a doubles team (with a `note`
 * explaining why) — distinct from `0`, which means the fields apply and none
 * are known. Kept as a plain array so those nulls decode without fatal.
 */
final class Player extends Model
{
    public ?int $id = null;
    public ?string $name = null;

    /** Opaque tour string — do NOT parse into the filter enum. */
    public ?string $tour = null;

    public ?string $country = null;
    public ?int $ranking = null;
    public ?int $ranking_points = null;
    public ?string $ranking_movement = null;
    public ?string $hand = null;
    public ?int $backhand = null;

    /** ISO 8601 date string exactly as received. */
    public ?string $birthday = null;

    public ?bool $is_doubles_team = null;

    /**
     * How much biographical detail is known. `known`/`of` are null on a doubles
     * team (see the `note` key). May be null entirely outside a match payload.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data_completeness = null;

    /**
     * Ratings + season stats. Populated only by the single-player endpoint.
     *
     * @var array<string, mixed>|null
     */
    public ?array $stats = null;
}
