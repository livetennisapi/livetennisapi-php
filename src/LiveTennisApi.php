<?php

declare(strict_types=1);

namespace LiveTennisApi;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use LiveTennisApi\Exception\ApiConnectionError;
use LiveTennisApi\Exception\ApiTimeoutError;
use LiveTennisApi\Exception\ErrorFactory;
use LiveTennisApi\Model\Analysis;
use LiveTennisApi\Model\Event;
use LiveTennisApi\Model\Fixture;
use LiveTennisApi\Model\ListMeta;
use LiveTennisApi\Model\Market;
use LiveTennisApi\Model\Model;
use LiveTennisApi\Model\Page;
use LiveTennisApi\Model\Player;
use LiveTennisApi\Model\Score;
use LiveTennisApi\Model\TennisMatch;
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
    public const VERSION = '1.0.0';
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
     * Matches by lifecycle status: `live`, `upcoming` or `completed`.
     *
     * @return Page<TennisMatch>
     */
    public function listMatches(
        string $status = 'live',
        ?string $tour = null,
        int $limit = 50,
        int $offset = 0,
    ): Page {
        return $this->page(
            $this->request('/matches', $this->params([
                'status' => $status,
                'tour' => $tour,
                'limit' => $limit,
                'offset' => $offset,
            ])),
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
     * Completed matches, newest first, with a derived `winner`. **BASIC.**
     *
     * @return Page<TennisMatch>
     */
    public function listCompletedMatches(int $limit = 50, int $offset = 0): Page
    {
        return $this->page(
            $this->request('/history/matches', $this->params([
                'limit' => $limit,
                'offset' => $offset,
            ])),
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
     * Walk every page of a list endpoint, yielding items one at a time.
     *
     *     foreach ($client->paginate('searchPlayers', ['djokovic']) as $player) { … }
     *
     * Stops when a page comes back short — the only reliable end-of-data signal,
     * since `meta.count` describes the page, not the total.
     *
     * @param array<int, mixed> $args Positional args preceding limit/offset.
     * @return \Generator<int, Model>
     */
    public function paginate(string $method, array $args = [], int $pageSize = self::MAX_LIMIT): \Generator
    {
        $pageSize = max(1, min($pageSize, self::MAX_LIMIT));
        $offset = 0;

        while (true) {
            /** @var Page<Model> $page */
            $page = $this->{$method}(...[...$args, $pageSize, $offset]);
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
     * @param array<string, mixed> $params
     * @return mixed Decoded JSON body (array|scalar|null).
     */
    private function request(string $path, array $params = []): mixed
    {
        $url = $this->url($path);
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $last = null;
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->http->sendRequest($this->buildRequest($url));
            } catch (NetworkExceptionInterface $e) {
                $timedOut = stripos($e->getMessage(), 'timed out') !== false
                    || stripos($e->getMessage(), 'timeout') !== false;
                $last = $timedOut
                    ? new ApiTimeoutError("request to {$url} timed out after {$this->timeout}s", 0, $e)
                    : new ApiConnectionError("could not reach {$url}: {$e->getMessage()}", 0, $e);
                if ($attempt >= $this->maxRetries) {
                    throw $last;
                }
                ($this->sleeper)($this->backoff($attempt, null));
                continue;
            } catch (ClientExceptionInterface $e) {
                $last = new ApiConnectionError("could not reach {$url}: {$e->getMessage()}", 0, $e);
                if ($attempt >= $this->maxRetries) {
                    throw $last;
                }
                ($this->sleeper)($this->backoff($attempt, null));
                continue;
            }

            $status = $response->getStatusCode();
            if (ErrorFactory::shouldRetry($status) && $attempt < $this->maxRetries) {
                ($this->sleeper)($this->backoff($attempt, ErrorFactory::retryAfterSeconds($this->flattenHeaders($response))));
                continue;
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
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function requestArray(string $path, array $params = []): ?array
    {
        $body = $this->request($path, $params);

        return is_array($body) ? $body : null;
    }

    private function buildRequest(string $url): \Psr\Http\Message\RequestInterface
    {
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        if ($this->apiKey !== '') {
            $request = $this->authHeader === 'bearer'
                ? $request->withHeader('Authorization', "Bearer {$this->apiKey}")
                : $request->withHeader('X-API-Key', $this->apiKey);
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
     * @param mixed              $body
     * @param class-string<Model> $model
     * @return Page<Model>
     */
    private function page(mixed $body, string $model): Page
    {
        if (is_array($body) && array_key_exists('data', $body)) {
            $items = is_array($body['data']) ? $body['data'] : [];
            $meta = isset($body['meta']) && is_array($body['meta']) ? ListMeta::fromArray($body['meta']) : null;
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
