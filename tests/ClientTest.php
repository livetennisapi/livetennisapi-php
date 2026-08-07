<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests;

use LiveTennisApi\Exception\AbuseThrottled;
use LiveTennisApi\Exception\ApiConnectionError;
use LiveTennisApi\Exception\ApiTimeoutError;
use LiveTennisApi\Exception\BadRequest;
use LiveTennisApi\Exception\NotFound;
use LiveTennisApi\Exception\RateLimited;
use LiveTennisApi\Exception\ServerError;
use LiveTennisApi\Exception\Unauthorized;
use LiveTennisApi\Exception\UpgradeRequired;
use LiveTennisApi\Model\Player;
use LiveTennisApi\Model\Score;
use LiveTennisApi\Model\TennisMatch;
use LiveTennisApi\Tests\Support\MockClient;
use LiveTennisApi\Tests\Support\NetworkFailure;
use LiveTennisApi\Tests\Support\TestCase;

final class ClientTest extends TestCase
{
    public function testHealthNeedsNoDecode(): void
    {
        $mock = (new MockClient())->queueJson(['status' => 'ok', 'version' => 'v1']);
        $this->assertSame(['status' => 'ok', 'version' => 'v1'], $this->client($mock)->health());
    }

    public function testLiveMatchesDecodeWithStringPoints(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('live_matches'));
        $page = $this->client($mock)->listMatches('live');

        $this->assertGreaterThan(0, count($page));
        $match = $page[0];
        $this->assertInstanceOf(TennisMatch::class, $match);
        $this->assertInstanceOf(Score::class, $match->score);

        // points are STRINGS, never ints
        $this->assertIsArray($match->score->points);
        foreach ($match->score->points as $p) {
            $this->assertIsString($p);
        }
        // games are player-major per-set sub-arrays
        $this->assertIsArray($match->score->games);
    }

    public function testUpcomingMatchNullScoreDoesNotFatal(): void
    {
        // Required scenario: a null Score must not fatal the decode.
        $mock = (new MockClient())->queueJson($this->fixture('upcoming_null_score'));
        $page = $this->client($mock)->listMatches('upcoming');

        $this->assertGreaterThan(0, count($page));
        foreach ($page as $match) {
            $this->assertInstanceOf(TennisMatch::class, $match);
            $this->assertNull($match->score, 'upcoming match score must decode to null');
            $this->assertSame('upcoming', $match->status);
        }
    }

    public function testNullServerLiveMatchDecodes(): void
    {
        // score.server is nullable (verified against the live contract).
        $mock = (new MockClient())->queueJson($this->fixture('live_match_null_server'));
        $match = $this->client($mock)->getMatch(22313);

        $this->assertInstanceOf(TennisMatch::class, $match);
        $this->assertInstanceOf(Score::class, $match->score);
        $this->assertNull($match->score->server, 'server must decode to null without error');
        $this->assertIsArray($match->score->points);
    }

    public function testDoublesMatchNullDataCompleteness(): void
    {
        // data_completeness.known/of are null on a doubles team, with a note.
        $mock = (new MockClient())->queueJson($this->fixture('doubles_match'));
        $match = $this->client($mock)->getMatch(22210);

        $this->assertInstanceOf(TennisMatch::class, $match);
        $this->assertTrue($match->is_doubles);

        $p1 = $match->p1();
        $this->assertInstanceOf(Player::class, $p1);
        $this->assertIsArray($p1->data_completeness);
        $this->assertNull($p1->data_completeness['known']);
        $this->assertNull($p1->data_completeness['of']);
        $this->assertArrayHasKey('note', $p1->data_completeness);

        // tour is the granular/UPPERCASE record tour, kept as an opaque string.
        $this->assertSame('ATP', $p1->tour);
    }

    public function testSearchPlayersDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('players_search'));
        $page = $this->client($mock)->searchPlayers('djokovic');

        $this->assertCount(1, $page);
        $this->assertInstanceOf(Player::class, $page[0]);
        $this->assertSame('Novak Djokovic', $page[0]->name);
        // ranked player; tour null on this record
        $this->assertNull($page[0]->tour);
        $this->assertSame(8, $page[0]->ranking);
    }

    public function test403ThrowsUpgradeRequiredWithTier(): void
    {
        // Required scenario: 403 -> UpgradeRequired, with inferred tier.
        $mock = (new MockClient())->queueJson(['error' => 'upgrade_required'], 403);

        try {
            $this->client($mock)->getMatchAnalysis(1);
            $this->fail('expected UpgradeRequired');
        } catch (UpgradeRequired $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('upgrade_required', $e->errorCode());
            $this->assertSame('ULTRA', $e->getRequiredTier());
            $this->assertStringContainsString('ULTRA', $e->getMessage());
        }
    }

    public function test403IsNeverRetried(): void
    {
        // Only one response queued; a retry would exhaust the queue and throw
        // a different error. Getting UpgradeRequired proves no retry happened.
        $mock = (new MockClient())->queueJson(['error' => 'upgrade_required'], 403);
        $client = $this->client($mock, ['max_retries' => 3]);

        $this->expectException(UpgradeRequired::class);
        $client->getMatchAnalysis(1);
    }

    /**
     * @return array<string, array{0: int, 1: class-string}>
     */
    public static function statusCases(): array
    {
        return [
            '400' => [400, BadRequest::class],
            '401' => [401, Unauthorized::class],
            '403' => [403, UpgradeRequired::class],
            '404' => [404, NotFound::class],
            '500' => [500, ServerError::class],
        ];
    }

    /**
     * @dataProvider statusCases
     * @param class-string $expected
     */
    public function testErrorTaxonomyMapping(int $status, string $expected): void
    {
        $mock = (new MockClient())->queueJson(['error' => 'boom'], $status);
        // max_retries 0 so a 500 surfaces immediately rather than retrying.
        $this->expectException($expected);
        $this->client($mock, ['max_retries' => 0])->getPlayer(1);
    }

    public function testRateLimitedCarriesRetryAfter(): void
    {
        $mock = (new MockClient())->queueJson(['error' => 'rate_limited'], 429, ['Retry-After' => '12']);
        $client = $this->client($mock, ['max_retries' => 0]);

        try {
            $client->listMatches('live');
            $this->fail('expected RateLimited');
        } catch (RateLimited $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame(12.0, $e->getRetryAfter());
            $this->assertStringContainsString('retry after 12s', $e->getMessage());
        }
    }

    public function testRetriesThen429ThenSucceeds(): void
    {
        $mock = (new MockClient())
            ->queueJson(['error' => 'rate_limited'], 429, ['Retry-After' => '0'])
            ->queueJson($this->fixture('live_matches'));

        $page = $this->client($mock, ['max_retries' => 2])->listMatches('live');
        $this->assertGreaterThan(0, count($page));
        $this->assertCount(2, $mock->requests, 'should have retried once');
    }

    public function testServerErrorRetriesThenSucceeds(): void
    {
        $mock = (new MockClient())
            ->queueJson(['error' => 'boom'], 503)
            ->queueJson($this->fixture('players_search'));

        $page = $this->client($mock, ['max_retries' => 2])->searchPlayers('djokovic');
        $this->assertCount(1, $page);
    }

    public function testTourFilterIsPassedThrough(): void
    {
        $mock = (new MockClient())->queueJson(['data' => [], 'meta' => []]);
        $this->client($mock)->listMatches('live', 'atp');

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('tour=atp', $uri);
        $this->assertStringContainsString('status=live', $uri);
    }

    public function testBadTourValueSurfacesBadRequest(): void
    {
        // The API 400s on an unrecognised tour rather than silently passing it.
        $mock = (new MockClient())->queueJson(['error' => 'invalid tour'], 400);
        $this->expectException(BadRequest::class);
        $this->client($mock)->listMatches('live', 'football');
    }

    public function testBearerAuthHeaderByDefault(): void
    {
        $mock = (new MockClient())->queueJson(['status' => 'ok']);
        $this->client($mock)->health();
        $this->assertSame('Bearer twjp_test', $mock->lastRequest()?->getHeaderLine('Authorization'));
    }

    public function testXApiKeyAuthHeaderMode(): void
    {
        $mock = (new MockClient())->queueJson(['status' => 'ok']);
        $this->client($mock, ['auth_header' => 'x-api-key'])->health();
        $this->assertSame('twjp_test', $mock->lastRequest()?->getHeaderLine('X-API-Key'));
        $this->assertSame('', $mock->lastRequest()?->getHeaderLine('Authorization'));
    }

    public function testNetworkTimeoutBecomesApiTimeoutError(): void
    {
        $mock = new MockClient();
        $factory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        $mock->queueResponse(new NetworkFailure('connection timed out', $factory->createRequest('GET', 'https://x')));

        $this->expectException(ApiTimeoutError::class);
        $this->client($mock, ['max_retries' => 0])->health();
    }

    public function testNetworkDropBecomesApiConnectionError(): void
    {
        $mock = new MockClient();
        $factory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        $mock->queueResponse(new NetworkFailure('connection refused', $factory->createRequest('GET', 'https://x')));

        $this->expectException(ApiConnectionError::class);
        $this->client($mock, ['max_retries' => 0])->health();
    }

    // -- 1.1.0: list filters ---------------------------------------------------

    public function testJuniorsTourFilterIsPassedThrough(): void
    {
        $mock = (new MockClient())->queueJson(['data' => [], 'meta' => []]);
        $this->client($mock)->listMatches('upcoming', 'juniors');

        $this->assertStringContainsString('tour=juniors', (string) $mock->lastRequest()?->getUri());
    }

    public function testPlayerFilterRepeatsTheKey(): void
    {
        $mock = (new MockClient())->queueJson(['data' => [], 'meta' => []]);
        $this->client($mock)->listMatches('completed', null, 50, 0, [
            'player' => [3819, 4210],
            'country' => 'ned',
            'from' => '2026-08-01',
            'to' => '2026-08-07',
        ]);

        $uri = (string) $mock->lastRequest()?->getUri();
        // explode form — player=1&player=2, never player[0]=1
        $this->assertStringContainsString('player=3819&player=4210', $uri);
        $this->assertStringNotContainsString('player%5B', $uri);
        $this->assertStringContainsString('country=ned', $uri);
        $this->assertStringContainsString('from=2026-08-01', $uri);
        $this->assertStringContainsString('to=2026-08-07', $uri);
    }

    public function testSinglePlayerFilterNeedsNoArray(): void
    {
        $mock = (new MockClient())->queueJson(['data' => [], 'meta' => []]);
        $this->client($mock)->listCompletedMatches(50, 0, ['player' => 3819]);

        $this->assertStringContainsString('player=3819', (string) $mock->lastRequest()?->getUri());
    }

    public function testMoreThanFiftyPlayerIdsThrowBeforeAnyRequest(): void
    {
        $mock = new MockClient();

        try {
            $this->client($mock)->listMatches('completed', null, 50, 0, ['player' => range(1, 51)]);
            $this->fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('50', $e->getMessage());
        }

        $this->assertCount(0, $mock->requests, 'the cap must be enforced client-side');
    }

    public function testUnknownFilterKeyThrows(): void
    {
        // Unknown KEYS would be dropped silently by the gateway (it only
        // 400s on unknown values), so the client refuses them.
        $this->expectException(\InvalidArgumentException::class);
        $this->client(new MockClient())->listMatches('live', null, 50, 0, ['countryy' => 'ned']);
    }

    // -- 1.1.0: 429 taxonomy ---------------------------------------------------

    public function testDaily429SurfacesResetsAt(): void
    {
        $mock = (new MockClient())->queueJson([
            'error' => 'rate_limited',
            'scope' => 'day',
            'limit_per_day' => 100,
            'resets_at' => '2026-08-07T22:00:00+00:00',
        ], 429, ['Retry-After' => '26400']);

        try {
            $this->client($mock, ['max_retries' => 0])->listMatches('live');
            $this->fail('expected RateLimited');
        } catch (RateLimited $e) {
            $this->assertTrue($e->isDaily());
            $this->assertSame('day', $e->getScope());
            $this->assertSame(100, $e->getLimitPerDay());
            // an absolute ISO instant — never a fixed UTC hour to assume
            $this->assertSame('2026-08-07T22:00:00+00:00', $e->getResetsAt());
        }
    }

    public function testMinute429HasNoDailyFields(): void
    {
        $mock = (new MockClient())->queueJson(['error' => 'rate_limited'], 429, ['Retry-After' => '7']);

        try {
            $this->client($mock, ['max_retries' => 0])->listMatches('live');
            $this->fail('expected RateLimited');
        } catch (RateLimited $e) {
            $this->assertFalse($e->isDaily());
            $this->assertNull($e->getResetsAt());
            $this->assertSame(7.0, $e->getRetryAfter());
        }
    }

    public function testAbuseThrottledCarriesRetryAtEpoch(): void
    {
        $mock = (new MockClient())->queueJson([
            'error' => 'abuse_throttled',
            'retry_at_epoch' => 1786572000,
        ], 429);

        try {
            $this->client($mock, ['max_retries' => 0])->listMatches('live');
            $this->fail('expected AbuseThrottled');
        } catch (AbuseThrottled $e) {
            $this->assertSame('abuse_throttled', $e->errorCode());
            $this->assertSame(1786572000, $e->getRetryAtEpoch());
            $this->assertInstanceOf(RateLimited::class, $e, 'catch-alls on RateLimited still catch it');
        }
    }

    public function testAbuseThrottledIsNeverRetried(): void
    {
        // Only one response queued: a retry would exhaust the queue and fail
        // differently. The block punishes retry loops; retrying it is wrong.
        $mock = (new MockClient())->queueJson([
            'error' => 'abuse_throttled',
            'retry_at_epoch' => 1786572000,
        ], 429);
        $client = $this->client($mock, ['max_retries' => 3]);

        $this->expectException(AbuseThrottled::class);
        try {
            $client->listMatches('live');
        } finally {
            $this->assertCount(1, $mock->requests, 'abuse_throttled must not be retried');
        }
    }

    public function testOrdinary429IsStillRetried(): void
    {
        $mock = (new MockClient())
            ->queueJson(['error' => 'rate_limited'], 429, ['Retry-After' => '0'])
            ->queueJson(['data' => [], 'meta' => []]);

        $this->client($mock, ['max_retries' => 2])->listMatches('live');
        $this->assertCount(2, $mock->requests);
    }
}
