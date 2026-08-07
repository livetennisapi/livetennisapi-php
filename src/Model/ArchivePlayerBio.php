<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One archive person — hand, date of birth, country, height, career-high.
 * BASIC tier and above (or any History plan).
 *
 * `id` is the corpus person id that archive match rows carry as
 * `winner.player_id` / `loser.player_id`, scoped per tour — never a roster
 * id. Career-high rank and the earliest week it was reached are computed
 * offline from the corpus's own weekly ranking tables. Null fields are the
 * era's silence, never guessed.
 */
final class ArchivePlayerBio extends Model
{
    /** Corpus person id, scoped per tour. NOT a roster id. */
    public ?int $id = null;

    /** `atp` or `wta`. */
    public ?string $tour = null;

    public ?string $name = null;
    public ?string $hand = null;

    /** ISO 8601 date string exactly as received. */
    public ?string $dob = null;

    public ?string $country = null;
    public ?int $height_cm = null;
    public ?int $career_high_rank = null;

    /** The earliest week the career-high rank was reached. */
    public ?string $career_high_date = null;
}
