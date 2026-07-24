<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Model analysis for a match. ULTRA tier only; either half may be null.
 *
 * Kept as plain nested arrays: the `thesis`/`profile` shapes are rich and
 * evolving, so exposing them raw avoids silently dropping fields.
 */
final class Analysis extends Model
{
    /** @var array<string, mixed>|null */
    public ?array $thesis = null;

    /** @var array<string, mixed>|null */
    public ?array $profile = null;
}
