<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

/**
 * A match.
 *
 * NOTE ON THE NAME: the sibling clients call this `Match`, but `match` is a
 * reserved keyword in PHP 8+ and cannot be a class name, so it is `TennisMatch`
 * here. The API resource and JSON shape are identical.
 *
 * `score` is nullable — an upcoming match has no score yet, and the field
 * arrives as JSON `null`, so callers must null-check it.
 *
 * `market` is present from PRO, `analysis` from ULTRA — both are ABSENT (not
 * null) below those tiers, so treat `null` as "not entitled / not available"
 * rather than "no market exists".
 */
final class TennisMatch extends Model
{
    public ?int $id = null;
    public ?string $tournament = null;
    public ?string $surface = null;
    public ?bool $indoor = null;
    public ?string $format = null;
    public ?string $round = null;
    public ?string $status = null;
    public ?string $event_status = null;
    public ?bool $is_doubles = null;

    /** ISO 8601 UTC string exactly as received. */
    public ?string $scheduled_time = null;

    /**
     * `{p1: Player, p2: Player}`, hydrated to {@see Player} instances.
     *
     * @var array<string, Player>|null
     */
    public ?array $players = null;

    /** Nullable — an upcoming match has no score. */
    public ?Score $score = null;

    /** Completed matches only — derived from final sets. 1, 2, or null. */
    public ?int $winner = null;

    /** PRO+ only (absent below). */
    public ?Market $market = null;

    /** ULTRA only (absent below). */
    public ?Analysis $analysis = null;

    protected function hydrate(array $data): void
    {
        // Score arrives as an object or JSON null; only build when present.
        $this->score = isset($data['score']) && is_array($data['score'])
            ? Score::fromArray($data['score'])
            : null;

        $this->market = isset($data['market']) && is_array($data['market'])
            ? Market::fromArray($data['market'])
            : null;

        $this->analysis = isset($data['analysis']) && is_array($data['analysis'])
            ? Analysis::fromArray($data['analysis'])
            : null;

        if (isset($data['players']) && is_array($data['players'])) {
            $players = [];
            foreach ($data['players'] as $key => $val) {
                $players[$key] = is_array($val) ? Player::fromArray($val) : $val;
            }
            $this->players = $players;
        }
    }

    /** Player 1, or null if the payload had no players object. */
    public function p1(): ?Player
    {
        $p = $this->players['p1'] ?? null;

        return $p instanceof Player ? $p : null;
    }

    /** Player 2, or null if the payload had no players object. */
    public function p2(): ?Player
    {
        $p = $this->players['p2'] ?? null;

        return $p instanceof Player ? $p : null;
    }
}
