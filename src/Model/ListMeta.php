<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Pagination envelope returned alongside list responses.
 *
 * NOTE: `count` describes THIS page, not the total result set. The only
 * reliable end-of-data signal is a short page.
 */
final class ListMeta extends Model
{
    public ?int $limit = null;
    public ?int $offset = null;
    public ?int $count = null;
}
