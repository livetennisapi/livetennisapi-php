<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * Pagination envelope returned alongside list responses.
 *
 * NOTE: `count` describes THIS page, not the total result set. `total` is
 * the size of the whole filtered set — null when it cannot be counted
 * cheaply (e.g. `/matches?status=completed`). Read `has_more` rather than
 * comparing `count` to `limit` to decide whether another page exists.
 */
class ListMeta extends Model
{
    public ?int $limit = null;
    public ?int $offset = null;
    public ?int $count = null;

    /** Size of the whole filtered set; null when it cannot be counted cheaply. */
    public ?int $total = null;

    /** More results exist beyond this page. */
    public ?bool $has_more = null;
}
