<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One charted point, in play order.
 *
 * `raw` is the charter's own string, verbatim, and is ALWAYS present; the
 * parsed fields are our reading of it. `parsed` is false when the notation
 * contained something we could not read cleanly — the recognised part is
 * still returned. A consumer who wants only unambiguous rows filters on
 * `parsed`.
 */
final class RallyPoint extends Model
{
    public ?int $point = null;

    /** `[p1, p2]` set count entering the point (entries may be null). @var array<int, int|null>|null */
    public ?array $set = null;

    /** `[p1, p2]` game count entering the point (entries may be null). @var array<int, int|null>|null */
    public ?array $games = null;

    /** e.g. '30-40'. */
    public ?string $score = null;

    public ?int $game = null;
    public ?bool $is_tiebreak = null;

    /** 1, 2, or null. */
    public ?int $server = null;

    /** 1, 2, or null. */
    public ?int $point_winner = null;

    /** The charter's shot string; both serves joined by ';' when the first was a fault. */
    public ?string $raw_code = null;

    public ?bool $parsed = null;

    /** 1, 2, or null. */
    public ?int $serve_number = null;

    /** wide|body|down_the_t|null. */
    public ?string $serve_direction = null;

    /** Strokes including the serve. An ace is 1, a double fault 0. */
    public ?int $rally_length = null;

    /**
     * winner|forced_error|unforced_error|error|other|null. `error` = the
     * charter recorded a miss without saying whether it was forced — never
     * guessed into one of the specific kinds.
     */
    public ?string $outcome = null;

    /** net|wide|deep|wide_and_deep|null. */
    public ?string $error_location = null;

    public ?string $ending_stroke = null;
    public ?string $ending_wing = null;
    public ?bool $is_ace = null;
    public ?bool $is_double_fault = null;
    public ?bool $is_serve_and_volley = null;

    /** @var array<int, RallyShot> */
    public array $shots = [];

    protected function hydrate(array $data): void
    {
        // The API calls the charter's verbatim string `raw`, which collides
        // with Model::$raw (the whole payload). Exposed as `raw_code`; the
        // original key is still readable via `$point->raw['raw']`.
        if (array_key_exists('raw', $data) && (is_string($data['raw']) || $data['raw'] === null)) {
            $this->raw_code = $data['raw'];
        }

        if (isset($data['shots']) && is_array($data['shots'])) {
            $this->shots = array_values(array_filter(array_map(
                static fn ($s): ?RallyShot => is_array($s) ? RallyShot::fromArray($s) : null,
                $data['shots'],
            )));
        }
    }
}
