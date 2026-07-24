# Live Tennis API — PHP client

Official PHP client for the [Live Tennis API](https://livetennisapi.com): real-time
tennis scores, players, fixtures, match-winner market prices and model win-probability
for ATP, WTA, Challenger and ITF.

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
    echo $match->tournament, ': ',
         $match->p1()?->name, ' vs ', $match->p2()?->name, "\n";

    // score is nullable — an upcoming match has none yet
    if ($match->score !== null) {
        // points are STRINGS ("40", "AD"); games are player-major per-set lists
        echo '  ', implode('-', $match->score->points), "\n";
    }
}
```

Auth defaults to `Authorization: Bearer <key>`; pass `['auth_header' => 'x-api-key']`
to send `X-API-Key` instead.

## Methods

| Method | Endpoint | Tier |
| --- | --- | --- |
| `health()` | `/health` | — |
| `listMatches($status, $tour, $limit, $offset)` | `/matches` | FREE |
| `getMatch($id)` | `/matches/{id}` | FREE (+market PRO, +analysis ULTRA) |
| `getMatchScore($id)` | `/matches/{id}/score` | FREE |
| `listMatchEvents($id, …)` | `/matches/{id}/events` | PRO |
| `getMatchAnalysis($id)` | `/matches/{id}/analysis` | ULTRA |
| `searchPlayers($search, …)` | `/players` | FREE |
| `getPlayer($id)` | `/players/{id}` | FREE |
| `listMarkets($id)` | `/markets` | PRO |
| `getMarketPrices($id, $limit)` | `/markets/{id}/prices` | PRO |
| `listCompletedMatches(…)` | `/history/matches` | BASIC |
| `listFixtures($tour, …)` | `/fixtures` | FREE |

`paginate('searchPlayers', ['djokovic'])` walks every page, yielding items.

## Errors

Every non-2xx maps onto a typed exception, all extending `LiveTennisApi\Exception\LiveTennisApiError`:

```php
use LiveTennisApi\Exception\{UpgradeRequired, RateLimited, NotFound};

try {
    $client->getMatchAnalysis($id);          // ULTRA-only
} catch (UpgradeRequired $e) {
    echo $e->getRequiredTier();               // "ULTRA"
} catch (RateLimited $e) {
    sleep((int) ($e->getRetryAfter() ?? 60));
}
```

`ApiStatusError` carries `getStatusCode()`, `getBody()`, `getHeaders()`,
`getRequestUrl()` and `errorCode()`. `429` and `5xx` are retried with backoff
(honouring `Retry-After`); other `4xx` are not.

## Notes on the data (verified against live JSON)

- `score` is nullable; `score.server` is `1`, `2`, or **null**.
- `points` are **strings** (`"40"`, `"AD"`).
- `games` is player-major: `[[6,3], [4,6]]` reads 6-4, 3-6. Use `gamesForSet($i)`.
- A player/fixture `tour` is the record's own granular string (`juniors_boys`,
  and UPPERCASE `ATP` for a doubles team) — **not** the `tour` filter enum.
- On a doubles team, `data_completeness.known`/`of` are `null` (with a `note`).
- The `tour` **filter** accepts `atp|wta|challenger|itf|juniors`; an unknown
  value is a `400` (`BadRequest`).
- Unknown response fields are never rejected — they are kept in `->raw` and
  readable as properties.

## Development

```bash
composer install
vendor/bin/phpunit          # runs entirely offline against recorded fixtures
LIVETENNISAPI_KEY=twjp_… php examples/smoke.php   # optional live check
```

## License

MIT.
