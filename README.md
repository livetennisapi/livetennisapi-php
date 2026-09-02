<div align="center">

<img src="https://raw.githubusercontent.com/livetennisapi/.github/main/profile/banner.jpg" alt="Live Tennis API" width="640">

# livetennisapi-php

**Official PHP client for the [Live Tennis API](https://livetennisapi.com).**

Real-time tennis scores, players, fixtures, head-to-heads, a 1968→now results
archive, point-by-point tapes, shot-by-shot rally data, match-winner market
prices and model win-probability — for ATP, WTA, Challenger, ITF and juniors.

[![ci](https://github.com/livetennisapi/livetennisapi-php/actions/workflows/ci.yml/badge.svg)](https://github.com/livetennisapi/livetennisapi-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/livetennisapi/livetennisapi)](https://packagist.org/packages/livetennisapi/livetennisapi)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

[**Documentation**](https://docs.livetennisapi.com) · [**Get a free API key**](https://livetennisapi.com/subscribe/free)

</div>

---

Modern PHP 8.1+, PSR-4, PSR-12. Transport is **PSR-18** — bring any HTTP client
(Guzzle, Symfony HttpClient, …) and the library auto-discovers it. Method names,
error taxonomy and tier handling mirror the official
[Python](https://github.com/livetennisapi/livetennisapi-python) and
[JS](https://github.com/livetennisapi/livetennisapi-js) clients.

## Install

```bash
composer require livetennisapi/livetennisapi
# plus any PSR-18 client + PSR-17 factories, e.g.
composer require guzzlehttp/guzzle
```

## Quick start

```php
use LiveTennisApi\LiveTennisApi;

$client = new LiveTennisApi('twjp_…');      // or set LIVETENNISAPI_KEY

foreach ($client->listMatches('live') as $match) {
    echo $match->tournament, ' [', $match->tour, ']: ',
         $match->p1()?->name, ' vs ', $match->p2()?->name, "\n";

    // score is nullable — an upcoming match has none yet
    if ($match->score !== null) {
        // points are STRINGS ("40", "AD"); games are player-major per-set lists
        echo '  ', implode('-', $match->score->points), "\n";
    }
}
```

Get a free key at [livetennisapi.com/subscribe/free](https://livetennisapi.com/subscribe/free)
— no card needed. A free key allows 100 requests/day, so poll no faster than
every 15 minutes; an always-on dashboard should run on BASIC or above.

## Methods

| Method | Endpoint | Tier |
| --- | --- | --- |
| `health()` | `/health` | — |
| `listMatches($status, $tour, …, $filters)` | `/matches` | FREE (`status=completed` BASIC) |
| `getMatch($id)` | `/matches/{id}` | FREE (+market PRO, +analysis ULTRA) |
| `getMatchScore($id)` | `/matches/{id}/score` | FREE |
| `listMatchEvents($id, …)` | `/matches/{id}/events` | PRO |
| `getMatchAnalysis($id)` | `/matches/{id}/analysis` | ULTRA |
| `getMatchStatistics($id)` | `/matches/{id}/statistics` | ULTRA |
| `searchPlayers($search, …)` | `/players` | FREE |
| `getPlayer($id)` | `/players/{id}` | FREE |
| `listMarkets($id)` | `/markets` | PRO |
| `getMarketPrices($id, $limit)` | `/markets/{id}/prices` | PRO |
| `listMatchPrices($id, $limit, $minutes)` | `/matches/{id}/prices` | PRO |
| `listFixtures($tour, …)` | `/fixtures` | FREE |
| `listTournaments($search, $tour, …)` | `/tournaments` | FREE |
| `getTournament($id)` | `/tournaments/{id}` | FREE |
| `getUsage()` | `/usage` | any (quota-exempt) |
| `listCompletedMatches(…, $filters)` | `/history/matches` | BASIC¹ |
| `getHistoryMatch($id, $sequence)` | `/history/matches/{id}` | BASIC¹ |
| `getHeadToHead($p1, $p2)` | `/h2h` | BASIC¹ |
| `listArchiveMatches($filters, …)` | `/history/archive/matches` | BASIC¹ |
| `getArchiveMatch($id)` | `/history/archive/matches/{id}` | BASIC¹ |
| `listArchivePlayers($filters, …)` | `/history/archive/players` | BASIC¹ |
| `getArchiveCareer($name)` | `/history/archive/career` | BASIC¹ |
| `listRankings($players, $asOf, $systems, …)` | `/rankings` | PRO listing / ULTRA per-player² |
| `listHistoryPackages($kind, $year)` | `/history/packages` | PRO¹ ³ |
| `getHistoryPackage($period, $kind)` | `/history/packages/{period}` | PRO¹ ³ |
| `listRallyMatches($filters, …)` | `/rally/matches` | ULTRA |
| `getRallyMatch($id, …)` | `/rally/matches/{id}` | ULTRA |
| `getMatchRally($matchId, …)` | `/history/matches/{id}/rally` | ULTRA |
| `getChartingPlayer($name, $gender)` | `/charting/players` | ULTRA |
| `getChartingMatch($id)` | `/charting/matches/{id}` | ULTRA |
| `getWsToken()` | `/ws-token` | ULTRA |
| `createWebhook($url, $events)` | `POST /webhooks` | ULTRA⁴ |
| `listWebhooks()` | `/webhooks` | ULTRA⁴ |
| `deleteWebhook($id)` | `DELETE /webhooks/{id}` | ULTRA⁴ |

¹ Also unlocked by [History plans](https://livetennisapi.com/products) — a
History grant works even on a free core key.
² Without `$players` (PRO): the full published table in rank order for exactly
one system. With `$players` ids (ULTRA): per-player point-in-time records —
the newest record effective on or before `$asOf`, never one dated after it.
³ `$kind` is `tape` (default) | `rankings` | `rally` | `archive`.
`rankings` and `rally` (the yearly charted-rally exports) need ULTRA;
`archive` (yearly 1968–2022 results exports) has the same entitlement as the
tape packages. The yearly kinds use a bare-year `YYYY` period. The `?year=`
archive listing needs ULTRA (or the matching History package/plan).
⁴ Direct keys only (RapidAPI keys get 403 `direct_key_required`); max 3
webhooks per key (a 409 `Conflict`, `webhook_limit`). The signing `secret`
is returned exactly once, on the create response — store it.

`paginate('searchPlayers', ['djokovic'])` walks every page, yielding items;
filters ride along as trailing args:
`paginate('listCompletedMatches', [], 200, [['tour' => 'wta']])`.

New in 1.1.0, list endpoints accept a `$filters` array — `player` (id or list
of ids, max 50, matching EITHER participant), `country` (lowercase 3-letter
IOC-style code, e.g. `ned`), `from`/`to` date bounds — and the `tour` filter
vocabulary is `atp|wta|challenger|itf|juniors`. Unknown filter values are a
`400` (`BadRequest`), never silently ignored.

## Quotas

| Tier | Requests/min | Requests/day | Price |
| --- | --- | --- | --- |
| FREE | 30 | 100/day | $0 |
| BASIC | 60 | 1,000/day | $9.99/mo |
| PRO | 300 | 10,000/day | $29.99/mo |
| ULTRA | 600 | 500,000/day | $99.99/mo |

Every response carries `X-RateLimit-Limit` / `X-RateLimit-Remaining` /
`X-RateLimit-Reset` headers for the per-minute window, and a `Retry-After`
header on 429.

## Auth

Auth defaults to `Authorization: Bearer <key>` (preferred); pass
`['auth_header' => 'x-api-key']` to send `X-API-Key` instead. Keys look like
`twjp_…` and can also come from the `LIVETENNISAPI_KEY` environment variable.

## Errors

Every non-2xx maps onto a typed exception, all extending `LiveTennisApi\Exception\LiveTennisApiError`:

```php
use LiveTennisApi\Exception\{UpgradeRequired, RateLimited, AbuseThrottled, NotFound};

try {
    $client->getMatchAnalysis($id);          // ULTRA-only
} catch (UpgradeRequired $e) {
    echo $e->getRequiredTier();               // "ULTRA"
} catch (AbuseThrottled $e) {
    // 24h block for chronic over-cap clients — fix the retry loop,
    // don't work around the block. Never auto-retried by this client.
    echo $e->getRetryAtEpoch();               // unix time the block lifts
} catch (RateLimited $e) {
    if ($e->isDaily()) {
        echo $e->getResetsAt();               // absolute ISO instant the daily quota resets
    }
    sleep((int) ($e->getRetryAfter() ?? 60));
}
```

`ApiStatusError` carries `getStatusCode()`, `getBody()`, `getHeaders()`,
`getRequestUrl()` and `errorCode()`. `429` and `5xx` are retried with backoff
(honouring `Retry-After`); other `4xx` — and the `abuse_throttled` 429 — are
not. The daily-quota 429 exposes `getScope()` (`"day"`), `getLimitPerDay()`
and `getResetsAt()` — an absolute ISO 8601 instant, deliberately not a fixed
UTC hour.

## Notes on the data (verified against live JSON)

- `score` is nullable; `score.server` is `1`, `2`, or **null**.
- `points` are **strings** (`"40"`, `"AD"`).
- `games` is player-major: `[[6,3], [4,6]]` reads 6-4, 3-6. Use `gamesForSet($i)`.
- `Match.tour` uses the filter vocabulary (`atp|wta|challenger|itf|juniors`,
  null for exhibitions/team events) and is safe to group on. A player/fixture
  `tour` is the record's own granular string (`juniors_boys`, and UPPERCASE
  `ATP` for a doubles team) — treat that one as opaque.
- `Match.tournament_id` joins the `/tournaments` catalogue; `round_code` is
  the normalized round (`F`, `SF`, `QF`, `R16`, …), null when the free-text
  label is unrecognised — never guessed.
- `event_status` says how a match ended early (`Retired`, `Walk Over`,
  `Interrupted`, …); `withdrew` (1|2) says who retired/conceded — present only
  when derivable.
- `has_analysis` / `has_market` (every tier, since 2026-09-02) say whether a
  model thesis/profile, or a match-winner market, exists for the match.
  Filter a slate on them before calling `/matches/{id}/analysis` or
  `/markets/{id}/prices`, which answer `404` (`no_analysis` / `no_market`)
  about the same fact. Null only when talking to an older server.
- On the tape (`getHistoryMatch`), `?sequence=clean` rows carry
  `point_winner`; raw rows never do (raw is deliberately non-monotonic —
  consecutive raw rows are corrections, not points). Reconstructed rows have a
  null `timestamp` and null model fields — nothing is synthesised. Check
  `meta['coverage']` / `meta['point_source']` before backtesting.
- Rankings: systems are never collapsed — UTR carries a `rating` with null
  rank/points. `previous_rank` is ATP/WTA only. Listing rows carry
  `player_name` as published, with a null `player_id` for players outside the
  roster.
- On a doubles team, `data_completeness.known`/`of` are `null` (with a `note`).
- Unknown response fields are never rejected — they are kept in `->raw` and
  readable as properties.

## Development

```bash
composer install
vendor/bin/phpunit          # runs entirely offline against recorded fixtures
LIVETENNISAPI_KEY=twjp_… php examples/smoke.php   # optional live check
```

## Links

- Docs: [docs.livetennisapi.com](https://docs.livetennisapi.com)
- Free API key: [livetennisapi.com/subscribe/free](https://livetennisapi.com/subscribe/free)
- Products & History plans: [livetennisapi.com/products](https://livetennisapi.com/products)
- Discord: [discord.gg/f8WUZHgDm6](https://discord.gg/f8WUZHgDm6)
- GitHub org: [github.com/livetennisapi](https://github.com/livetennisapi)

## License

MIT.

## Affiliate program

Know developers who need tennis data? The [affiliate program](https://affiliates.livetennisapi.com/program) pays 51% recurring commission for the life of every referred subscription — 30-day cookie, and the people you refer get 10% off.
