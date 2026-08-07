<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A tournament from the catalogue — the id space `Match.tournament_id`
 * joins. FREE.
 *
 * One row per tournament × event type, stable across seasons. `category`
 * (e.g. `grand_slam`, `masters_1000`) is present only where the catalogues
 * agree unambiguously on an exact-name join — null otherwise, never derived
 * from the name.
 *
 * NOTE: `country` here is the HOST country in ISO-3166 alpha-2 (`GB`), a
 * different vocabulary from `Player.country` (IOC-style 3-letter). Both
 * `city` and `country` come from a curated table and are null where not
 * curated.
 */
final class Tournament extends Model
{
    /** The stable id `Match.tournament_id` joins. A string, not an int. */
    public ?string $id = null;

    public ?string $name = null;

    /** atp|wta|challenger|itf|juniors|null — the filter vocabulary. */
    public ?string $tour = null;

    /** hard|clay|grass|null. */
    public ?string $surface = null;

    public ?bool $indoor = null;

    /** Host city, from a curated table — null where not curated. */
    public ?string $city = null;

    /** Host country, ISO-3166 alpha-2 — null where not curated. */
    public ?string $country = null;

    /**
     * grand_slam|masters_1000|tour_finals|atp_500|atp_250|wta_1000|wta_500|
     * wta_250|wta_125|challenger|itf|juniors|null — never guessed from the
     * name.
     */
    public ?string $category = null;
}
