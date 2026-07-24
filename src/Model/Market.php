<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A match-winner market. PRO tier and above.
 */
final class Market extends Model
{
    public ?int $id = null;
    public ?string $question = null;
    public ?string $status = null;
    public ?float $volume = null;
    public ?float $liquidity = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $end_date = null;

    /** @var array<int, Price> Newest first. */
    public array $prices = [];

    protected function hydrate(array $data): void
    {
        if (isset($data['prices']) && is_array($data['prices'])) {
            $this->prices = array_values(array_filter(array_map(
                static fn ($p): ?Price => is_array($p) ? Price::fromArray($p) : null,
                $data['prices'],
            )));
        }
    }
}
