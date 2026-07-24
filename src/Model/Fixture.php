<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A scheduled fixture. Players are names only — not yet resolved to ids.
 *
 * `tour` is the record's OWN granular/opaque tour string (see {@see Player}),
 * NOT the `tour` filter vocabulary.
 */
final class Fixture extends Model
{
    public ?int $id = null;

    /** ISO 8601 date string exactly as received. */
    public ?string $event_date = null;

    /** Opaque tour string — do NOT parse into the filter enum. */
    public ?string $tour = null;

    public ?string $tournament = null;
    public ?string $round = null;
    public ?string $surface = null;
    public ?string $player1_name = null;
    public ?string $player2_name = null;
    public ?string $status = null;
}
