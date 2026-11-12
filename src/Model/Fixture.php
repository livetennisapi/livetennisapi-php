<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A scheduled fixture.
 *
 * Names are always present. `player1_id` / `player2_id` are our roster ids
 * where the participant resolves by exact key — null otherwise, which is a
 * real state, not an omission.
 *
 * `tour` is the record's OWN granular/opaque tour string (see {@see Player}),
 * NOT the `tour` filter vocabulary.
 */
final class Fixture extends Model
{
    public ?int $id = null;

    /** ISO 8601 date string exactly as received. */
    public ?string $event_date = null;

    /**
     * Scheduled start, as an ISO 8601 UTC string exactly as received. Null
     * until the order of play assigns a time — a date-only fixture is a real
     * state.
     */
    public ?string $start_time = null;

    /** Our player id, when the participant is in our roster. Null otherwise. */
    public ?int $player1_id = null;

    /** As `player1_id`, for the second participant. */
    public ?int $player2_id = null;

    /** Opaque tour string — do NOT parse into the filter enum. */
    public ?string $tour = null;

    public ?string $tournament = null;
    public ?string $round = null;

    /**
     * Normalized round — same vocabulary as `TennisMatch::$round_code`. Null
     * when the label is unrecognised — never guessed.
     */
    public ?string $round_code = null;

    public ?string $surface = null;
    public ?string $player1_name = null;
    public ?string $player2_name = null;
    public ?string $status = null;

    /**
     * Why a fixture ended without being played out:
     * `walkover|retired|cancelled|default|postponed`. `status` folds the
     * first four into `cancelled`; this field names which one. Null on a
     * scheduled, live or finished fixture.
     */
    public ?string $reason = null;

    /**
     * The `id` of the match this fixture became, which every match route
     * accepts. Null while no match exists yet — a fixture can publish days
     * before its match. Set when found and never cleared.
     */
    public ?int $match_id = null;

    /**
     * The sex of the event (`men|women`), in the vocabulary of the `gender`
     * filter. Null for mixed doubles and mixed team events.
     */
    public ?string $gender = null;

    /**
     * Whether the fixture is in the qualifying draw. Three-valued, like
     * `TennisMatch::$is_qualifying`: NULL means never stated, and NULL is not
     * false. The qualifying draw is published under the main draw's
     * tournament name, so group a schedule on `round_code` and this flag
     * together.
     */
    public ?bool $is_qualifying = null;

    /**
     * When this row first existed, as an ISO 8601 UTC string. A `created_at`
     * inside your polling window is a new row.
     */
    public ?string $created_at = null;

    /**
     * When this row last changed, as an ISO 8601 UTC string. A moved
     * `updated_at` with `created_at` unchanged is a change to a row you
     * already hold, such as a re-listed start time.
     */
    public ?string $updated_at = null;

    /**
     * ULTRA only (absent below). The bound match's `stage: pregame`
     * forecasts, oldest first, each in the `Analysis::$profile` shape. `[]`
     * when the fixture has no `match_id` yet or no forecast has been written.
     * NULL means the field was not sent — "not entitled", never "no forecast".
     *
     * @var list<array<string, mixed>>|null
     */
    public ?array $pregame_forecasts = null;
}
