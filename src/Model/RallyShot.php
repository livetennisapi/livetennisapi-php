<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One stroke of a charted point. Shots are numbered from the serve: serve 1,
 * return 2, the server's next ball 3.
 *
 * `code` is the charter's raw code (e.g. `f`); the other fields are our
 * reading of it, null where the notation did not say.
 */
final class RallyShot extends Model
{
    public ?int $number = null;

    /** The charter's raw code, e.g. 'f'. */
    public ?string $code = null;

    /** serve|groundstroke|slice|volley|half_volley|swinging_volley|overhead|drop_shot|lob|trick|unknown|null. */
    public ?string $stroke = null;

    /** The side it was struck FROM: forehand|backhand|null. */
    public ?string $wing = null;

    /** Where the ball was sent: forehand_side|middle|backhand_side|null. */
    public ?string $direction = null;

    /** shallow|mid|deep|null. */
    public ?string $depth = null;

    /** approaching|at_net|baseline|null. */
    public ?string $position = null;
}
