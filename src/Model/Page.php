<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A single page of a list endpoint: `{data, meta}`.
 *
 * Iterable, countable and indexable so it reads like the item list it wraps,
 * while still exposing the pagination `meta`.
 *
 * @template T of Model
 * @implements IteratorAggregate<int, T>
 * @implements ArrayAccess<int, T>
 */
final class Page implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @param array<int, T> $data
     */
    public function __construct(
        public array $data = [],
        public ?ListMeta $meta = null,
        public array $raw = [],
    ) {
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}
