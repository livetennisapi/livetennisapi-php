<?php

declare(strict_types=1);

namespace LiveTennisApi;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use LiveTennisApi\Exception\ApiConnectionError;
use LiveTennisApi\Exception\ApiTimeoutError;
use LiveTennisApi\Exception\ErrorFactory;
use LiveTennisApi\Model\Analysis;
use LiveTennisApi\Model\ArchiveCareer;
use LiveTennisApi\Model\ArchiveMatch;
use LiveTennisApi\Model\ArchivePlayerBio;
use LiveTennisApi\Model\ChartingMatch;
use LiveTennisApi\Model\ChartingPlayer;
use LiveTennisApi\Model\Event;
use LiveTennisApi\Model\Fixture;
use LiveTennisApi\Model\HeadToHead;
use LiveTennisApi\Model\HistoryPackage;
use LiveTennisApi\Model\HistoryTape;
use LiveTennisApi\Model\ListMeta;
use LiveTennisApi\Model\Market;
use LiveTennisApi\Model\MatchStatistics;
use LiveTennisApi\Model\Model;
use LiveTennisApi\Model\Page;
use LiveTennisApi\Model\Player;
use LiveTennisApi\Model\Price;
use LiveTennisApi\Model\RallyMatch;
use LiveTennisApi\Model\RankingListMeta;
use LiveTennisApi\Model\RankingRecord;
use LiveTennisApi\Model\Score;
use LiveTennisApi\Model\TennisMatch;
use LiveTennisApi\Model\Tournament;
use LiveTennisApi\Model\Usage;
use LiveTennisApi\Model\Webhook;
use LiveTennisApi\Model\WsToken;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Synchronous client for the Live Tennis API.
 *
 *     use LiveTennisApi\LiveTennisApi;
 *
 *     $client = new LiveTennisApi('twjp_…');            // or reads LIVETENNISAPI_KEY
 *     foreach ($client->listMatches('live') as $match) {
 *         echo $match->tournament, ' ', $match->p1()?->name, PHP_EOL;
 *     }
 *
 * Transport is PSR-18: the client auto-discovers any installed PSR-18 HTTP
 * client and PSR-17 request factory (Guzzle, Symfony HttpClient, …), or you can
 * inject your own via the `http_client` / `request_factory` options — which is
 * how the test suite runs entirely offline.
 *
 * Method names, error semantics and tier handling mirror the official Python
 * and JS clients so behaviour is consistent across languages.
 */
final class LiveTennisApi
{
    public const VERSION = '1.1.0';
    public const DEFAULT_BASE_URL = 'https://api.livetennisapi.com/api/public/v1';
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_RETRIES = 2;
    public const MAX_LIMIT = 200;

    private string $apiKey;
    private string $baseUrl;
    private float $timeout;
    private int $maxRetries;
    private string $authHeader;
    private string $userAgent;
    private ClientInterface $http;
    private RequestFactoryInterface $requestFactory;

    /** @var callable(float): void Sleep hook; overridable so tests never wait. */
    private $sleeper;

    /**
     * @param string|null          $apiKey  Falls back to the LIVETENNISAPI_KEY env var.
     * @param array<string, mixed> $options base_url, timeout, max_retries, auth_header
     *                                      ('bearer'|'x-api-key'), user_agent, http_client
     *                                      (PSR-18), request_factory (PSR-17), sleeper.
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey ?? (getenv('LIVETENNISAPI_KEY') ?: '');
        $this->apiKey = trim($key);

        $base = $options['base_url'] ?? (getenv('LIVETENNISAPI_BASE_URL') ?: self::DEFAULT_BASE_URL);
        $this->baseUrl = rtrim((string) $base, '/');

        $this->timeout = (float) ($options['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->maxRetries = max(0, (int) ($options['max_retries'] ?? self::DEFAULT_MAX_RETRIES));

        $authHeader = strtolower((string) ($options['auth_header'] ?? 'bearer'));
        if (!in_array($authHeader, ['bearer', 'x-api-key'], true)) {
            throw new \InvalidArgumentException("auth_header must be 'bearer' or 'x-api-key'");
        }
        $this->authHeader = $authHeader;

        $this->userAgent = (string) ($options['user_agent'] ?? 'livetennisapi-php/' . self::VERSION);

        $this->http = $options['http_client'] ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $options['request_factory'] ?? Psr17FactoryDiscovery::findRequestFactory();

        $this->sleeper = $options['sleeper'] ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
    }

    // -- endpoints ------------------------------------------------------------

    /**
     * Liveness probe. Needs no authentication.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $body = $this->request('/health');

        return is_array($body) ? $body : [];
    }

    /**
     * Matches by lifecycle status: `live`, `upcoming` or `completed`
     * (`completed` needs BASIC+ or any History plan).
     *
     * `$filters` (all optional): `player` — id or list of ids (max 50),
     * matches where the id is EITHER participant; `country` — lowercase
     * 3-letter code, the vocabulary `Player.country` returns (IOC-style,
     * e.g. `ned`, NOT ISO-3166); `from`/`to` — play-date bounds,
     * YYYY-MM-DD or ISO 8601 UTC datetime. `$tour` accepts
     * `atp|wta|challenger|itf|juniors`. Unknown filter VALUES are a 400
     * (`BadRequest`), never silently ignored.
     *
     * @param array<string, mixed> $filters
     * @return Page<TennisMatch>
     */
    public function listMatches(
        string $status = 'live',
        ?string $tour = null,
        int $limit = 50,
        int $offset = 0,
        array $filters = [],
    ): Page {
        return $this->page(
            $this->request('/matches', $this->params([
                'status' => $status,
                'tour' => $tour,
                'limit' => $limit,
                'offset' => $offset,
            ]) + $this->filterParams($filters, ['player', 'country', 'from', 'to'])),
            TennisMatch::class,
        );
    }

    /**
     * Full match detail. Embeds `market` at PRO and `analysis` at ULTRA.
     */
    public function getMatch(int $matchId): ?TennisMatch
    {
        return TennisMatch::fromArray($this->requestArray("/matches/{$matchId}"));
    }

    /**
     * Current score only — the lowest-latency read available. May be null.
     */
    public function getMatchScore(int $matchId): ?Score
    {
        return Score::fromArray($this->requestArray("/matches/{$matchId}/score"));
    }

    /**
     * Match events, newest first. **PRO.**
     *
     * @return Page<Event>
     */
    public function listMatchEvents(int $matchId, int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request("/matches/{$matchId}/events", $this->params([
                'limit' => $limit,
                'offset' => $offset,
            ])),
            Event::class,
        );
    }

    /**
     * Model analysis for a match. **ULTRA.**
     */
    public function getMatchAnalysis(int $matchId): ?Analysis
    {
        return Analysis::fromArray($this->requestArray("/matches/{$matchId}/analysis"));
    }

    /**
     * Search players by name. Ranked players come first.
     *
     * @return Page<Player>
     */
    public function searchPlayers(?string $search = null, int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request('/players', $this->params([
                'search' => $search,
                'limit' => $limit,
                'offset' => $offset,
            ])),
            Player::class,
        );
    }

    /**
     * One player's bio, ranking and cached stats.
     */
    public function getPlayer(int $playerId): ?Player
    {
        return Player::fromArray($this->requestArray("/players/{$playerId}"));
    }

    /**
     * Match-winner market(s) for a match. **PRO.**
     *
     * @return Page<Market>
     */
    public function listMarkets(int $matchId): Page
    {
        return $this->page(
            $this->request('/markets', ['match_id' => $matchId]),
            Market::class,
        );
    }

    /**
     * Market with recent price ticks per side, newest first. **PRO.**
     */
    public function getMarketPrices(int $matchId, int $limit = 50): ?Market
    {
        return Market::fromArray($this->requestArray("/markets/{$matchId}/prices", ['limit' => $limit]));
    }

    /**
     * Bare price ticks of the mapped match-winner market, newest first (no
     * market wrapper). **PRO.** `$limit` caps at 500; `$minutes` bounds the
     * lookback window (max 1440). 404 when the match has no mapped market.
     *
     * There is no offset here — the page's `meta->has_more` means the
     * window was clipped at `$limit`; raise the limit or narrow `$minutes`.
     *
     * @return Page<Price>
     */
    public function listMatchPrices(int $matchId, ?int $limit = null, ?int $minutes = null): Page
    {
        return $this->page(
            $this->request("/matches/{$matchId}/prices", $this->params([
                'limit' => $limit,
                'minutes' => $minutes,
            ])),
            Price::class,
        );
    }

    /**
     * Completed matches, newest first, with a derived `winner` and a `tape`
     * coverage block per row. **BASIC** (or any History plan).
     *
     * `$filters` (all optional): `from`/`to` (play-date bounds), `tour`
     * (`atp|wta|challenger|itf|juniors`), `player` (id or list of ids, max
     * 50), `country` (lowercase 3-letter code), `coverage` (keep only
     * matches whose tape has this coverage — NOTE it is applied AFTER the
     * page is cut, so a filtered page is routinely shorter than `limit`
     * while later pages still hold matches; a short filtered page is not an
     * end-of-data signal — read `meta->has_more`).
     *
     * @param array<string, mixed> $filters
     * @return Page<TennisMatch>
     */
    public function listCompletedMatches(int $limit = 50, int $offset = 0, array $filters = []): Page
    {
        return $this->page(
            $this->request('/history/matches', $this->params([
                'limit' => $limit,
                'offset' => $offset,
            ]) + $this->filterParams($filters, ['from', 'to', 'coverage', 'tour', 'player', 'country'])),
            TennisMatch::class,
        );
    }

    /**
     * Upcoming scheduled fixtures, earliest first.
     *
     * @return Page<Fixture>
     */
    public function listFixtures(?string $tour = null, int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request('/fixtures', $this->params([
                'tour' => $tour,
                'limit' => $limit,
                'offset' => $offset,
            ])),
            Fixture::class,
        );
    }

    /**
     * The tournament catalogue — the id space `Match.tournament_id` joins,
     * one row per tournament × event type, stable across seasons, in name
     * order. **FREE.** `$search` is a case-insensitive substring match on
     * the name; `$tour` is the filter vocabulary. Tier/category is only
     * present where the catalogues agree unambiguously.
     *
     * @return Page<Tournament>
     */
    public function listTournaments(
        ?string $search = null,
        ?string $tour = null,
        int $limit = 50,
        int $offset = 0,
    ): Page {
        return $this->page(
            $this->request('/tournaments', $this->params([
                'search' => $search,
                'tour' => $tour,
                'limit' => $limit,
                'offset' => $offset,
            ])),
            Tournament::class,
        );
    }

    /**
     * One tournament by its stable id — the `tournament_id` carried on
     * match objects. **FREE.**
     */
    public function getTournament(string $tournamentId): ?Tournament
    {
        return Tournament::fromArray($this->requestArray('/tournaments/' . rawurlencode($tournamentId)));
    }

    /**
     * Your own usage vs quota: tier, limits, today's calls and a 30-day
     * history. Any tier, and QUOTA-EXEMPT — polling it costs nothing. The
     * per-minute window lives on the `X-RateLimit-*` response headers, not
     * here.
     */
    public function getUsage(): ?Usage
    {
        return Usage::fromArray($this->requestArray('/usage'));
    }

    /**
     * Register an outbound webhook: the API POSTs the same frames the
     * WebSocket sends to `$url` (HTTPS, publicly routable) on every live
     * score commit. **ULTRA, direct keys only** (403 `direct_key_required`
     * on RapidAPI keys); max 3 webhooks per key (409 `Conflict`,
     * `webhook_limit`).
     *
     * The returned object's `secret` is shown EXACTLY ONCE, on this
     * response — store it; the list endpoint never includes it.
     *
     * @param array<int, string> $events `score` and/or `break_point`.
     */
    public function createWebhook(string $url, array $events = ['score']): ?Webhook
    {
        return Webhook::fromArray($this->requestArray('/webhooks', [], 'POST', [
            'url' => $url,
            'events' => array_values($events),
        ]));
    }

    /**
     * List your webhooks, with delivery health per row. **ULTRA, direct
     * keys only.** Never includes the signing secret.
     *
     * @return Page<Webhook>
     */
    public function listWebhooks(): Page
    {
        return $this->page($this->request('/webhooks'), Webhook::class);
    }

    /**
     * Remove one of your webhooks. **ULTRA, direct keys only.** Returns
     * true when the API confirms the deletion.
     */
    public function deleteWebhook(int $webhookId): bool
    {
        $body = $this->requestArray("/webhooks/{$webhookId}", [], 'DELETE');

        return is_int($body['deleted'] ?? null) && $body['deleted'] > 0;
    }

    /**
     * The point-by-point tape for one match: header + chronological score
     * sequence + model profiles + coverage meta. **BASIC** (or any History
     * plan). WORKS ON A LIVE MATCH, not only a completed one.
     *
     * `$sequence`: `raw` (default) is every committed row — deliberately
     * non-monotonic, since independent sources race and a higher-trust one
     * may correct a lower-trust one backwards; `clean` returns one row per
     * distinct score state (keeping the last assertion of each) and is the
     * only sequence whose rows carry `point_winner`. An unknown value is a
     * 400 `bad_sequence`.
     */
    public function getHistoryMatch(int $matchId, ?string $sequence = null): ?HistoryTape
    {
        return HistoryTape::fromArray($this->requestArray(
            "/history/matches/{$matchId}",
            $this->params(['sequence' => $sequence]),
        ));
    }

    /**
     * Head-to-head between two players across the results archive
     * (1968–2022) and our own completed matches (2023→now). **BASIC** (or
     * any History plan). Names are the keys — a fragment (min 3 chars)
     * matching more than one player is a 400 `ambiguous_name` listing the
     * candidates.
     */
    public function getHeadToHead(string $p1, string $p2): ?HeadToHead
    {
        return HeadToHead::fromArray($this->requestArray('/h2h', ['p1' => $p1, 'p2' => $p2]));
    }

    /**
     * Deep historical results, 1968–2022 — 1,485,752 matches, newest
     * tournament first. **BASIC** (or any History plan). A SEPARATE id
     * space from `/matches`; archive people are identified by name.
     *
     * `$filters` (all optional): `tour` (`atp|wta`), `name` (substring
     * match on EITHER player, min 3 chars), `from`/`to` (tournament START
     * dates, YYYY-MM-DD), `round` (F|SF|QF|R16|R32|R64|R128|RR|BR|Q1–Q4|ER),
     * `level` (source tier code: G, M, A, F, D, C, O, or a futures
     * category code).
     *
     * @param array<string, mixed> $filters
     * @return Page<ArchiveMatch>
     */
    public function listArchiveMatches(array $filters = [], int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request('/history/archive/matches', $this->filterParams(
                $filters,
                ['tour', 'name', 'from', 'to', 'round', 'level'],
            ) + ['limit' => $limit, 'offset' => $offset]),
            ArchiveMatch::class,
        );
    }

    /**
     * One archive result, with per-match serve statistics where the era
     * recorded them (`stats` is null on most rows before 1991 — never
     * synthesised). **BASIC** (or any History plan).
     */
    public function getArchiveMatch(int $archiveId): ?ArchiveMatch
    {
        return ArchiveMatch::fromArray($this->requestArray("/history/archive/matches/{$archiveId}"));
    }

    /**
     * Archive player bios — hand, date of birth, country, height,
     * career-high — ordered by name. **BASIC** (or any History plan).
     *
     * `$filters` (all optional): `name` (substring, min 3 chars), `tour`
     * (`atp|wta`).
     *
     * @param array<string, mixed> $filters
     * @return Page<ArchivePlayerBio>
     */
    public function listArchivePlayers(array $filters = [], int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request('/history/archive/players', $this->filterParams(
                $filters,
                ['name', 'tour'],
            ) + ['limit' => $limit, 'offset' => $offset]),
            ArchivePlayerBio::class,
        );
    }

    /**
     * One player's whole archive career (1968–2022): W-L record, titles,
     * summed serve stats. **BASIC** (or any History plan). `$name` must
     * resolve to exactly one person (400 `ambiguous_name` otherwise).
     */
    public function getArchiveCareer(string $name): ?ArchiveCareer
    {
        return ArchiveCareer::fromArray($this->requestArray('/history/archive/career', ['name' => $name]));
    }

    /**
     * Rankings. TWO MODES, gated differently:
     *
     *  - WITHOUT `$players` (**PRO**) — the FULL published table in rank
     *    order for exactly one `$systems` value, the newest week at or
     *    before `$asOf`; rows carry `player_name` as published and a null
     *    `player_id` for players outside our roster. `utr` has no listing
     *    (a rating, not a ranking).
     *  - WITH `$players` ids (**ULTRA**) — per-player point-in-time
     *    records: per system, the newest record effective ON OR BEFORE
     *    `$asOf`, never one dated after it.
     *
     * Systems: `atp|wta|itf_jt|itf_mt|itf_wt|utr`. The page's `meta` is a
     * {@see RankingListMeta} — read `coverage` before trusting an empty
     * result (ITF/UTR history begins 2026-07-29).
     *
     * @param array<int, int>          $players Max 50 ids.
     * @param string|array<int, string>|null $systems
     * @return Page<RankingRecord>
     */
    public function listRankings(
        array $players = [],
        ?string $asOf = null,
        string|array|null $systems = null,
        int $limit = 50,
        int $offset = 0,
    ): Page {
        if (count($players) > 50) {
            throw new \InvalidArgumentException('rankings accepts at most 50 player ids per request');
        }

        return $this->page(
            $this->request('/rankings', $this->params([
                'player' => $players === [] ? null : array_values($players),
                'as_of' => $asOf,
                'system' => $systems === null ? null : array_values((array) $systems),
                'limit' => $limit,
                'offset' => $offset,
            ])),
            RankingRecord::class,
            RankingListMeta::class,
        );
    }

    /**
     * In-play statistics for one match — aces, double faults, serve split,
     * hold/break %, break points, service & return points, in two families
     * (derived + measured) with per-family freshness. **ULTRA.**
     */
    public function getMatchStatistics(int $matchId): ?MatchStatistics
    {
        return MatchStatistics::fromArray($this->requestArray("/matches/{$matchId}/statistics"));
    }

    /**
     * Charted matches with shot-by-shot data, newest first. **ULTRA.**
     * Its own id space — ask this endpoint for the authoritative coverage
     * list rather than assuming a match is charted.
     *
     * `$filters` (all optional): `player` (substring match on either
     * player NAME), `from`/`to` (YYYY-MM-DD), `surface`, `gender` (`M|W`).
     *
     * @param array<string, mixed> $filters
     * @return Page<RallyMatch>
     */
    public function listRallyMatches(array $filters = [], int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request('/rally/matches', $this->filterParams(
                $filters,
                ['player', 'from', 'to', 'surface', 'gender'],
            ) + ['limit' => $limit, 'offset' => $offset]),
            RallyMatch::class,
        );
    }

    /**
     * Rally construction for one charted match — its points in play order,
     * paged with `$limit`/`$offset` (`meta->total` is the match's full
     * point count). **ULTRA.**
     */
    public function getRallyMatch(int $rallyMatchId, int $limit = 50, int $offset = 0): ?RallyMatch
    {
        return RallyMatch::fromArray($this->requestArray(
            "/rally/matches/{$rallyMatchId}",
            ['limit' => $limit, 'offset' => $offset],
        ));
    }

    /**
     * Rally construction addressed by OUR match id. **ULTRA.** Answers 404
     * `not_charted` when we hold the match but nobody charted it —
     * deliberately distinct from "no such match", because most matches are
     * not charted.
     */
    public function getMatchRally(int $matchId, int $limit = 50, int $offset = 0): ?RallyMatch
    {
        return RallyMatch::fromArray($this->requestArray(
            "/history/matches/{$matchId}/rally",
            ['limit' => $limit, 'offset' => $offset],
        ));
    }

    /**
     * Career shot-level charting aggregate for one player (Match Charting
     * Project). **ULTRA.** `$name` (min 3 chars) must resolve to one
     * charted person; `$gender` (`men|women`) disambiguates.
     */
    public function getChartingPlayer(string $name, ?string $gender = null): ?ChartingPlayer
    {
        return ChartingPlayer::fromArray($this->requestArray(
            '/charting/players',
            $this->params(['name' => $name, 'gender' => $gender]),
        ));
    }

    /**
     * One charted match — every stat family for both players, with the
     * per-set split exactly as charted. **ULTRA.** `$chartingMatchId` is
     * this product's own id space.
     */
    public function getChartingMatch(int $chartingMatchId): ?ChartingMatch
    {
        return ChartingMatch::fromArray($this->requestArray("/charting/matches/{$chartingMatchId}"));
    }

    /**
     * Mint a short-lived connection token for the push WebSocket feed.
     * **ULTRA.** Mint a fresh token on reconnect rather than caching one.
     */
    public function getWsToken(): ?WsToken
    {
        return WsToken::fromArray($this->requestArray('/ws-token'));
    }

    /**
     * List pre-built monthly bulk packages, newest period first. **PRO**
     * (or a package subscription).
     *
     * `$kind`: `tape` (default) = point-by-point match tapes; `rankings` =
     * as-of ranking records (**ULTRA**). `$year` (YYYY): every published
     * month of that year — needs History Business, a 1-year package, or
     * core ULTRA. Coverage is not a contiguous run of months; treat this
     * listing as the authoritative set of months that exist.
     *
     * @return Page<HistoryPackage>
     */
    public function listHistoryPackages(?string $kind = null, ?string $year = null): Page
    {
        return $this->page(
            $this->request('/history/packages', $this->params([
                'kind' => $kind,
                'year' => $year,
            ])),
            HistoryPackage::class,
        );
    }

    /**
     * One monthly package's JSON manifest (period YYYY-MM). **PRO** (or a
     * package subscription); `$kind = 'rankings'` requires ULTRA. The
     * manifest lists the downloadable files with sizes and sha256 — the
     * files themselves stream from the same URL with `?format=jsonl|csv`,
     * which this client does not buffer for you.
     */
    public function getHistoryPackage(string $period, ?string $kind = null): ?HistoryPackage
    {
        return HistoryPackage::fromArray($this->requestArray(
            "/history/packages/{$period}",
            $this->params(['kind' => $kind]),
        ));
    }

    /**
     * Walk every page of a list endpoint, yielding items one at a time.
     *
     *     foreach ($client->paginate('searchPlayers', ['djokovic']) as $player) { … }
     *
     * Stops when a page comes back short — the pragmatic end-of-data signal
     * (`meta.count` describes the page, not the total; `meta.has_more` is
     * the server's own word where you need it, e.g. under a `coverage`
     * filter that can legitimately return short pages mid-set).
     *
     * `$extraArgs` are appended AFTER limit/offset, for methods whose
     * filters array follows them:
     *
     *     $client->paginate('listCompletedMatches', [], 200, [['tour' => 'wta']]);
     *
     * @param array<int, mixed> $args      Positional args preceding limit/offset.
     * @param array<int, mixed> $extraArgs Positional args following limit/offset.
     * @return \Generator<int, Model>
     */
    public function paginate(
        string $method,
        array $args = [],
        int $pageSize = self::MAX_LIMIT,
        array $extraArgs = [],
    ): \Generator {
        $pageSize = max(1, min($pageSize, self::MAX_LIMIT));
        $offset = 0;

        while (true) {
            /** @var Page<Model> $page */
            $page = $this->{$method}(...[...$args, $pageSize, $offset, ...$extraArgs]);
            $items = $page->data;
            yield from $items;

            if (count($items) < $pageSize) {
                return;
            }
            $offset += $pageSize;
        }
    }

    // -- transport ------------------------------------------------------------

    /**
     * @param array<string, mixed>      $params
     * @param array<string, mixed>|null $json  JSON request body (POST only).
     * @return mixed Decoded JSON body (array|scalar|null).
     */
    private function request(string $path, array $params = [], string $method = 'GET', ?array $json = null): mixed
    {
        $url = $this->url($path);
        if ($params !== []) {
            $url .= '?' . $this->buildQuery($params);
        }

        // A POST is not idempotent: retrying one that may have reached the
        // server risks acting twice (e.g. registering a webhook twice), so
        // only GET/DELETE are ever retried.
        $idempotent = $method !== 'POST';

        $last = null;
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->http->sendRequest($this->buildRequest($url, $method, $json));
            } catch (NetworkExceptionInterface $e) {
                $timedOut = stripos($e->getMessage(), 'timed out') !== false
                    || stripos($e->getMessage(), 'timeout') !== false;
                $last = $timedOut
                    ? new ApiTimeoutError("request to {$url} timed out after {$this->timeout}s", 0, $e)
                    : new ApiConnectionError("could not reach {$url}: {$e->getMessage()}", 0, $e);
                if (!$idempotent || $attempt >= $this->maxRetries) {
                    throw $last;
                }
                ($this->sleeper)($this->backoff($attempt, null));
                continue;
            } catch (ClientExceptionInterface $e) {
                $last = new ApiConnectionError("could not reach {$url}: {$e->getMessage()}", 0, $e);
                if (!$idempotent || $attempt >= $this->maxRetries) {
                    throw $last;
                }
                ($this->sleeper)($this->backoff($attempt, null));
                continue;
            }

            $status = $response->getStatusCode();
            if ($idempotent && ErrorFactory::shouldRetry($status) && $attempt < $this->maxRetries) {
                // The abuse block (429 abuse_throttled) is a 24-hour ban on
                // chronically over-cap clients, not a transient window —
                // retrying IS the behaviour it punishes, so raise instead.
                if ($status !== 429 || !ErrorFactory::isAbuseThrottled($this->decode($response))) {
                    ($this->sleeper)($this->backoff($attempt, ErrorFactory::retryAfterSeconds($this->flattenHeaders($response))));
                    continue;
                }
            }

            $this->raiseForStatus($response, $path);

            return $this->decode($response);
        }

        throw $last ?? new ApiConnectionError("request to {$url} failed", 0);
    }

    /**
     * Like {@see request()} but guarantees an array (or null) for single-object
     * endpoints, so a stray non-object body decodes to null rather than a fatal.
     *
     * @param array<string, mixed>      $params
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>|null
     */
    private function requestArray(string $path, array $params = [], string $method = 'GET', ?array $json = null): ?array
    {
        $body = $this->request($path, $params, $method, $json);

        return is_array($body) ? $body : null;
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function buildRequest(string $url, string $method = 'GET', ?array $json = null): \Psr\Http\Message\RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        if ($this->apiKey !== '') {
            $request = $this->authHeader === 'bearer'
                ? $request->withHeader('Authorization', "Bearer {$this->apiKey}")
                : $request->withHeader('X-API-Key', $this->apiKey);
        }

        if ($json !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write((string) json_encode($json));
        }

        return $request;
    }

    private function raiseForStatus(ResponseInterface $response, string $path): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        $body = $this->decode($response);
        $headers = $this->flattenHeaders($response);
        $code = is_array($body) && isset($body['error']) && is_string($body['error']) ? $body['error'] : null;
        $message = $code ?? ($response->getReasonPhrase() ?: 'request failed');

        throw ErrorFactory::make($status, $message, $path, $body, $headers, $this->url($path));
    }

    /**
     * @return mixed Decoded JSON, or null when the body is empty/unparseable.
     */
    private function decode(ResponseInterface $response): mixed
    {
        $raw = (string) $response->getBody();
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Wrap a `{data, meta}` body into a {@see Page} of decoded models,
     * tolerating a bare list.
     *
     * @param mixed                  $body
     * @param class-string<Model>    $model
     * @param class-string<ListMeta> $metaClass
     * @return Page<Model>
     */
    private function page(mixed $body, string $model, string $metaClass = ListMeta::class): Page
    {
        if (is_array($body) && array_key_exists('data', $body)) {
            $items = is_array($body['data']) ? $body['data'] : [];
            $meta = isset($body['meta']) && is_array($body['meta']) ? $metaClass::fromArray($body['meta']) : null;
            $raw = $body;
        } else {
            $items = is_array($body) ? $body : [];
            $meta = null;
            $raw = ['data' => $items];
        }

        $decoded = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $obj = $model::fromArray($item);
                if ($obj !== null) {
                    $decoded[] = $obj;
                }
            }
        }

        return new Page($decoded, $meta, $raw);
    }

    /**
     * Drop null values so an unset argument never becomes a literal string.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function params(array $values): array
    {
        return array_filter($values, static fn ($v): bool => $v !== null);
    }

    /**
     * Validate a `$filters` array against the keys an endpoint accepts.
     *
     * Unknown KEYS throw here (they would otherwise be dropped silently by
     * the gateway, which only 400s on unknown VALUES). A `player` filter is
     * normalised to a list and capped at the API's documented 50 ids.
     *
     * @param array<string, mixed> $filters
     * @param array<int, string>   $allowed
     * @return array<string, mixed>
     */
    private function filterParams(array $filters, array $allowed): array
    {
        foreach (array_keys($filters) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "unknown filter '{$key}' — this endpoint accepts: " . implode(', ', $allowed),
                );
            }
        }

        if (isset($filters['player']) && is_array($filters['player'])) {
            $players = array_values($filters['player']);
            if (count($players) > 50) {
                throw new \InvalidArgumentException('the player filter accepts at most 50 ids per request');
            }
            $filters['player'] = $players;
        }

        return $this->params($filters);
    }

    /**
     * Build a query string, emitting an array value as a REPEATED key
     * (`player=1&player=2`) — the explode form the API expects for
     * `player` and `system` — where `http_build_query` would emit
     * `player[0]=1`.
     *
     * @param array<string, mixed> $params
     */
    private function buildQuery(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            foreach (is_array($value) ? array_values($value) : [$value] as $v) {
                if (is_bool($v)) {
                    $v = $v ? '1' : '0';
                }
                $pairs[] = rawurlencode($key) . '=' . rawurlencode((string) $v);
            }
        }

        return implode('&', $pairs);
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function flattenHeaders(ResponseInterface $response): array
    {
        $flat = [];
        foreach ($response->getHeaders() as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }

        return $flat;
    }

    /**
     * Seconds to wait before the next attempt. Honours the server's
     * `Retry-After` when present; otherwise exponential backoff with full
     * jitter, so concurrent clients don't retry in lockstep.
     */
    private function backoff(int $attempt, ?float $retryAfter): float
    {
        if ($retryAfter !== null) {
            return min($retryAfter, 60.0);
        }

        return min(0.5 * (2 ** $attempt) + (mt_rand() / mt_getrandmax()) * 0.25, 10.0);
    }
}
