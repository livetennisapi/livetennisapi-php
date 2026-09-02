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
 * rather than "no market exists". `has_market` / `has_analysis` answer the
 * existence question on every tier.
 */
final class TennisMatch extends Model
{
    public ?int $id = null;
    public ?string $tournament = null;

    /**
     * The tour, in the SAME vocabulary the `tour` query filter accepts
     * (`atp|wta|challenger|itf|juniors`) — a match selected by `?tour=X`
     * always carries that value here. Null when the feed never stated a
     * tour or the event type has no public tour name (exhibitions, team and
     * mixed events). Safe to group and filter on; never parse the
     * tournament name for this. (Unlike `Player.tour`, which is granular
     * and opaque.)
     */
    public ?string $tour = null;

    /**
     * Stable tournament identity — one id per tournament × event type,
     * stable across seasons. Joins `getTournament()` / `/tournaments/{id}`.
     * Null on matches ingested before the catalogue covered their
     * tournament.
     */
    public ?string $tournament_id = null;

    public ?string $surface = null;
    public ?bool $indoor = null;
    public ?string $format = null;
    public ?string $round = null;

    /**
     * The round in the archive's controlled vocabulary
     * (F|SF|QF|R16|R32|R64|R128|RR|BR|Q|Q1|Q2|Q3|Q4|ER), normalized from
     * the free-text `round` label ('Q' = qualifying round the feed does not
     * number). Null when the label is unrecognised — never guessed.
     */
    public ?string $round_code = null;

    public ?string $status = null;

    /**
     * How the match ended (or paused) when it did not run its course:
     * `Retired`, `Cancelled`, `Walk Over`, `Postponed`, or `Interrupted`
     * (an in-play suspension — the match is paused, not over). NULL means
     * the match completed normally OR the outcome was never resolved — the
     * feed does not distinguish those. The value is cleared if a suspended
     * match resumes.
     */
    public ?string $event_status = null;

    /**
     * The instant the current `event_status` was recorded, as an ISO 8601
     * UTC string (added 2026-08-19). Bumps only when the value changes — a
     * re-read of the same status never moves it — and a clear back to NULL
     * bumps it too. NULL while the status has never changed since the field
     * was introduced: never backfilled, never guessed.
     */
    public ?string $event_status_updated_at = null;

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

    /**
     * Completed matches only — which player retired or conceded the
     * walkover (1|2). Present only when `event_status` is Retired/Walk Over
     * and the winner is derivable; the withdrawer is the loser by the rules
     * of the sport.
     */
    public ?int $withdrew = null;

    /**
     * Whether a model thesis or profile exists for this match — on every
     * `/matches` row and the detail, every tier (since 2026-09-02). Filter
     * the slate on this before calling `/matches/{id}/analysis`, which
     * answers `404 no_analysis` about the same fact. NULL only when talking
     * to an older server that does not send it.
     */
    public ?bool $has_analysis = null;

    /**
     * Whether a match-winner market is mapped to this match (every tier,
     * since 2026-09-02). Same role for `/markets/{id}/prices`
     * (`404 no_market`). NULL only against an older server.
     */
    public ?bool $has_market = null;

    /**
     * History list rows only (`listCompletedMatches()`): what point-by-point
     * data we hold for this match — `{coverage, rows, reconstructed_rows}`.
     * `rows` counts rows we OBSERVED, not the length of the tape you will
     * be served; use the per-match tape's `meta['rows']` for that.
     *
     * @var array<string, mixed>|null
     */
    public ?array $tape = null;

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
