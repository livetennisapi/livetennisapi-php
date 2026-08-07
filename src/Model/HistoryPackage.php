<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A published monthly bulk package. PRO tier and above, or a package
 * subscription; `kind=rankings` and `?year=` archive listings carry their
 * own gates (see the client methods).
 *
 * Coverage is not a contiguous run of months and is still being extended
 * backwards, so treat the packages listing as the authoritative set of
 * months that exist. The JSONL file holds ONE LINE PER MATCH (a whole tape
 * object per line, coverage meta included), not one line per point; the CSV
 * is flattened to one row per point and carries no coverage columns.
 */
final class HistoryPackage extends Model
{
    /** YYYY-MM. */
    public ?string $period = null;

    /** Only built months are listed or served, so always `ready`. */
    public ?string $status = null;

    /** On a rankings package this is the number of players covered. */
    public ?int $match_count = null;

    /** On a rankings package this is the number of ranking records. */
    public ?int $row_count = null;

    /**
     * Downloadable files: `{format, filename, bytes, sha256}` each.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $files = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $built_at = null;

    /**
     * Package family — present only on non-tape packages (`rankings`), so
     * the shape a tape client already parses is unchanged. Absent means
     * `tape`.
     */
    public ?string $kind = null;
}
