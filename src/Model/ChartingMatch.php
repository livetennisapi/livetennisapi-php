<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * One charted match — every Match Charting Project stat family for both
 * players, with the per-set split (row/set 1, 2, Total) exactly as charted.
 * ULTRA tier only.
 *
 * `charting_match_id` is this product's own id space (1960–2026, mostly
 * matches with no counterpart in the live table); `mcp_id` is the Match
 * Charting Project's source key.
 */
final class ChartingMatch extends Model
{
    public ?int $charting_match_id = null;
    public ?string $mcp_id = null;

    /** M|W|null. */
    public ?string $gender = null;

    /** The two players as charted. @var array<string, mixed>|null */
    public ?array $players = null;

    /**
     * Per-family stat tables, keyed by family name, both players, with the
     * per-set split exactly as charted.
     *
     * @var array<string, mixed>|null
     */
    public ?array $families = null;
}
