<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests;

use LiveTennisApi\Model\ArchiveCareer;
use LiveTennisApi\Model\ArchiveMatch;
use LiveTennisApi\Model\ArchivePlayer;
use LiveTennisApi\Model\ArchivePlayerBio;
use LiveTennisApi\Model\ChartingMatch;
use LiveTennisApi\Model\ChartingPlayer;
use LiveTennisApi\Model\H2HMeeting;
use LiveTennisApi\Model\HeadToHead;
use LiveTennisApi\Model\HistoryPackage;
use LiveTennisApi\Model\HistoryTape;
use LiveTennisApi\Model\HistoryTapeRow;
use LiveTennisApi\Model\MatchStatistics;
use LiveTennisApi\Model\MatchStatisticsSide;
use LiveTennisApi\Exception\Conflict;
use LiveTennisApi\Model\Price;
use LiveTennisApi\Model\RallyMatch;
use LiveTennisApi\Model\RallyPoint;
use LiveTennisApi\Model\RankingListMeta;
use LiveTennisApi\Model\RankingRecord;
use LiveTennisApi\Model\TennisMatch;
use LiveTennisApi\Model\Tournament;
use LiveTennisApi\Model\Usage;
use LiveTennisApi\Model\Webhook;
use LiveTennisApi\Model\WsToken;
use LiveTennisApi\Tests\Support\MockClient;
use LiveTennisApi\Tests\Support\TestCase;

/**
 * Decode coverage for the 1.1.0 surface: /h2h, the results archive, rally
 * construction, charting, in-play statistics, rankings, ws-token, history
 * packages and the per-match tape.
 */
final class NewEndpointsTest extends TestCase
{
    public function testHeadToHeadDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('h2h'));
        $h2h = $this->client($mock)->getHeadToHead('djokovic', 'nadal');

        $this->assertInstanceOf(HeadToHead::class, $h2h);
        $this->assertSame(31, $h2h->totals['p1_wins']);
        $this->assertSame(1, $h2h->totals['undecided']);
        $this->assertSame(20, $h2h->by_surface['clay']['p2']);

        $this->assertCount(3, $h2h->meetings);
        $current = $h2h->meetings[0];
        $this->assertInstanceOf(H2HMeeting::class, $current);
        $this->assertSame('current', $current->era);
        $this->assertNull($current->score, 'current rows read their score from the match endpoints');
        // era-specific keys stay reachable through raw/__get
        $this->assertSame(18754, $current->match_id);

        $archive = $h2h->meetings[1];
        $this->assertSame('archive', $archive->era);
        $this->assertSame('6-2 4-6 6-2 7-6(4)', $archive->score);
        $this->assertSame(2, $archive->winner);
        $this->assertSame(1387211, $archive->archive_match_id);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('p1=djokovic', $uri);
        $this->assertStringContainsString('p2=nadal', $uri);
    }

    public function testArchiveMatchesDecodeWinnerLoserShape(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('archive_matches'));
        $page = $this->client($mock)->listArchiveMatches(['tour' => 'atp', 'level' => 'G']);

        $this->assertCount(2, $page);
        $final = $page[0];
        $this->assertInstanceOf(ArchiveMatch::class, $final);
        // winner is a stored FIELD, never an inference
        $this->assertInstanceOf(ArchivePlayer::class, $final->winner);
        $this->assertSame('Novak Djokovic', $final->winner->name);
        $this->assertSame(3, $final->winner->rank, 'rank is AT THE TIME, not current');
        $this->assertSame('completed', $final->outcome);
        $this->assertNull($final->stats, 'list rows carry no stats');

        // a 1969 row: null ranks/heights are the era's silence
        $laver = $page[1];
        $this->assertSame('retired', $laver->outcome);
        $this->assertSame('6-2 6-3 RET', $laver->score);
        $this->assertNull($laver->winner?->rank);

        // list meta: total + has_more decode
        $this->assertSame(1485752, $page->meta?->total);
        $this->assertTrue($page->meta?->has_more);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('tour=atp', $uri);
        $this->assertStringContainsString('level=G', $uri);
    }

    public function testArchiveMatchDetailCarriesStats(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('archive_match_detail'));
        $match = $this->client($mock)->getArchiveMatch(1402276);

        $this->assertInstanceOf(ArchiveMatch::class, $match);
        $this->assertIsArray($match->stats);
        $this->assertSame(30, $match->stats['loser']['aces']);
    }

    public function testArchivePlayersDecode(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('archive_players'));
        $page = $this->client($mock)->listArchivePlayers(['name' => 'williams']);

        $this->assertCount(2, $page);
        $serena = $page[0];
        $this->assertInstanceOf(ArchivePlayerBio::class, $serena);
        $this->assertSame(1, $serena->career_high_rank);
        $this->assertSame('2002-07-08', $serena->career_high_date);
        $this->assertStringContainsString('name=williams', (string) $mock->lastRequest()?->getUri());
    }

    public function testArchiveCareerDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('archive_career'));
        $career = $this->client($mock)->getArchiveCareer('laver');

        $this->assertInstanceOf(ArchiveCareer::class, $career);
        $this->assertSame(392, $career->record['wins']);
        $this->assertSame(41, $career->record['titles']);
        // pre-1991 corpus: zero matches with serve stats, ratios null not 0
        $this->assertSame(0, $career->serve['matches_with_stats']);
        $this->assertNull($career->serve['first_in_pct']);
    }

    public function testRallyMatchDecodesPointsAndShots(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('rally_match'));
        $match = $this->client($mock)->getRallyMatch(9120);

        $this->assertInstanceOf(RallyMatch::class, $match);
        $this->assertSame(9120, $match->rally_match_id);
        $this->assertNull($match->match_id, 'most charted matches predate our own collection');
        $this->assertSame(334, $match->points);

        $this->assertCount(2, $match->rally);
        $ace = $match->rally[0];
        $this->assertInstanceOf(RallyPoint::class, $ace);
        $this->assertTrue($ace->is_ace);
        $this->assertSame(1, $ace->rally_length, 'an ace is rally length 1');
        $this->assertSame(2, $ace->point_winner);
        $this->assertSame('a', $ace->raw_code, "the charter's verbatim string");

        $rally = $match->rally[1];
        $this->assertSame('unforced_error', $rally->outcome);
        $this->assertSame('6;f8b3f1n@', $rally->raw_code);
        $this->assertCount(4, $rally->shots);
        $this->assertSame('forehand', $rally->shots[1]->wing);

        // the rally page is bounded by meta.total = the full point count
        $this->assertSame(334, $match->meta?->total);
        $this->assertTrue($match->meta?->has_more);
    }

    public function testMatchRallyByOurIdHitsHistoryPath(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('rally_match'));
        $this->client($mock)->getMatchRally(21980, 100, 50);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('/history/matches/21980/rally', $uri);
        $this->assertStringContainsString('limit=100', $uri);
        $this->assertStringContainsString('offset=50', $uri);
    }

    public function testChartingPlayerDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('charting_player'));
        $player = $this->client($mock)->getChartingPlayer('swiatek', 'women');

        $this->assertInstanceOf(ChartingPlayer::class, $player);
        $this->assertSame(178, $player->matches_charted);
        $this->assertSame(688, $player->families['serve_direction']['deuce_t']);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('name=swiatek', $uri);
        $this->assertStringContainsString('gender=women', $uri);
    }

    public function testChartingMatchDecodesPerSetSplit(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('charting_match'));
        $match = $this->client($mock)->getChartingMatch(9120);

        $this->assertInstanceOf(ChartingMatch::class, $match);
        $this->assertSame(9120, $match->charting_match_id);
        $this->assertCount(4, $match->families['overview']);
        $this->assertSame('Total', $match->families['overview'][2]['set']);
    }

    public function testMatchStatisticsDecodeBothFamilies(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('match_statistics'));
        $stats = $this->client($mock)->getMatchStatistics(22313);

        $this->assertInstanceOf(MatchStatistics::class, $stats);
        $this->assertSame('live', $stats->coverage);
        $this->assertSame(1, $stats->tiebreak_games_excluded);

        $p1 = $stats->p1();
        $this->assertInstanceOf(MatchStatisticsSide::class, $p1);
        // derived family: typed
        $this->assertSame(89, $p1->hold_pct);
        $this->assertSame(4, $p1->break_points_faced);
        // measured family: keys as given, aces only exist here
        $this->assertSame(6, $p1->measured['aces']);
        $this->assertSame(65, $p1->measured['first_serves_in_pct']);

        // p2's measured block is sparser (absent fields are OMITTED, not zero)
        $p2 = $stats->p2();
        $this->assertInstanceOf(MatchStatisticsSide::class, $p2);
        $this->assertArrayNotHasKey('first_serves_in', $p2->measured);

        // per-family freshness is the branching surface
        $this->assertSame('live', $stats->freshness['derived']['coverage']);
        $this->assertNull($stats->freshness['measured_divergence']);
    }

    public function testRankingsListingModeDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('rankings_listing'));
        $page = $this->client($mock)->listRankings([], null, 'atp');

        $this->assertCount(3, $page);
        $first = $page[0];
        $this->assertInstanceOf(RankingRecord::class, $first);
        $this->assertSame(1, $first->rank);
        $this->assertSame(1, $first->previous_rank);
        $this->assertSame(3, $page[1]->previous_rank, 'previous_rank is the preceding snapshot week');

        // a published row for a player outside our roster: name, null id
        $hole = $page[2];
        $this->assertNull($hole->player_id);
        $this->assertSame('Botic van de Zandschulp', $hole->player_name);

        // rankings meta is the extended shape with coverage
        $this->assertInstanceOf(RankingListMeta::class, $page->meta);
        $this->assertSame(['atp'], $page->meta->coverage['systems_resolved']);
        $this->assertSame('2023-01-02', $page->meta->coverage['oldest_available']['atp']);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('system=atp', $uri);
        $this->assertStringNotContainsString('player=', $uri);
    }

    public function testRankingsPerPlayerModeSendsRepeatedIds(): void
    {
        $mock = (new MockClient())->queueJson(['data' => [], 'meta' => []]);
        $this->client($mock)->listRankings([3819, 4210], '2026-07-01', ['atp', 'utr']);

        $uri = (string) $mock->lastRequest()?->getUri();
        // explode form: repeated keys, never player[0]=
        $this->assertStringContainsString('player=3819&player=4210', $uri);
        $this->assertStringContainsString('system=atp&system=utr', $uri);
        $this->assertStringContainsString('as_of=2026-07-01', $uri);
        $this->assertStringNotContainsString('player%5B', $uri);
    }

    public function testRankingsRejectMoreThanFiftyPlayers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client(new MockClient())->listRankings(range(1, 51));
    }

    public function testWsTokenDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('ws_token'));
        $token = $this->client($mock)->getWsToken();

        $this->assertInstanceOf(WsToken::class, $token);
        $this->assertSame(300, $token->expires_in);
        $this->assertSame('wss://api.livetennisapi.com/connection/websocket', $token->ws_url);
        $this->assertSame('slate:all', $token->channels['slate']);
    }

    public function testHistoryPackagesDecode(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('history_packages'));
        $page = $this->client($mock)->listHistoryPackages();

        $this->assertCount(2, $page);
        $pkg = $page[0];
        $this->assertInstanceOf(HistoryPackage::class, $pkg);
        $this->assertSame('2026-07', $pkg->period);
        $this->assertNull($pkg->kind, 'tape packages carry no kind key');
        $this->assertSame('jsonl', $pkg->files[0]['format']);
        $this->assertSame(64, strlen($pkg->files[0]['sha256']));
    }

    public function testHistoryPackagesKindAndYearArePassedThrough(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('history_packages_rankings'));
        $page = $this->client($mock)->listHistoryPackages('rankings', '2026');

        $this->assertSame('rankings', $page[0]->kind);
        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('kind=rankings', $uri);
        $this->assertStringContainsString('year=2026', $uri);
    }

    public function testHistoryPackageManifestByPeriod(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('history_packages_rankings')['data'][0]);
        $pkg = $this->client($mock)->getHistoryPackage('2026-07', 'rankings');

        $this->assertInstanceOf(HistoryPackage::class, $pkg);
        $this->assertSame('rankings', $pkg->kind);
        $this->assertStringContainsString('/history/packages/2026-07?kind=rankings', (string) $mock->lastRequest()?->getUri());
    }

    public function testHistoryTapeCleanSequenceDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('history_tape_clean'));
        $tape = $this->client($mock)->getHistoryMatch(21980, 'clean');

        $this->assertInstanceOf(HistoryTape::class, $tape);
        $this->assertInstanceOf(TennisMatch::class, $tape->match);
        $this->assertSame('atp', $tape->match->tour);
        $this->assertSame('QF', $tape->match->round_code);

        $this->assertCount(4, $tape->tape);
        $first = $tape->tape[0];
        $this->assertInstanceOf(HistoryTapeRow::class, $first);
        $this->assertNull($first->point_winner, 'the first row has no attributable point');
        $this->assertSame(1, $tape->tape[1]->point_winner);
        $this->assertSame(2, $tape->tape[2]->point_winner);
        $this->assertTrue($tape->tape[2]->is_tiebreak);

        // per-set tiebreaks: {p1,p2} for the 7-6 set, null for the 6-4 set
        $this->assertSame(['p1' => 7, 'p2' => 5], $tape->tiebreaks[0]);
        $this->assertNull($tape->tiebreaks[1]);

        // coverage meta is the backtesting gate
        $this->assertSame('from_start', $tape->meta['coverage']);
        $this->assertSame('observed', $tape->meta['point_source']);
        $this->assertSame('clean', $tape->meta['sequence']);
        $this->assertSame(9, $tape->meta['raw_rows']);

        $this->assertStringContainsString('sequence=clean', (string) $mock->lastRequest()?->getUri());
    }

    public function testCompletedMatchesCarryNewMatchFields(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('completed_matches'));
        $page = $this->client($mock)->listMatches('completed');

        $retired = $page[0];
        $this->assertInstanceOf(TennisMatch::class, $retired);
        $this->assertSame('atp', $retired->tour);
        $this->assertSame('atp-cincinnati-singles', $retired->tournament_id);
        $this->assertSame('R32', $retired->round_code);
        $this->assertSame('Retired', $retired->event_status);
        $this->assertSame(1, $retired->winner);
        $this->assertSame(2, $retired->withdrew, 'the withdrawer is the loser');

        $normal = $page[1];
        $this->assertNull($normal->event_status);
        $this->assertNull($normal->withdrew);

        // total is null for status=completed; has_more is the signal
        $this->assertNull($page->meta?->total);
        $this->assertTrue($page->meta?->has_more);
    }

    // -- endpoint parity: tournaments, usage, match prices, webhooks -----------

    public function testTournamentsDecode(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('tournaments'));
        $page = $this->client($mock)->listTournaments('open', 'atp');

        $this->assertCount(2, $page);
        $cincy = $page[0];
        $this->assertInstanceOf(Tournament::class, $cincy);
        $this->assertSame('atp-cincinnati-singles', $cincy->id, 'tournament ids are strings');
        $this->assertSame('masters_1000', $cincy->category);
        $this->assertSame('US', $cincy->country, 'host country is ISO-3166 alpha-2, unlike player.country');

        // uncurated rows: null city/country/category, never guessed
        $itf = $page[1];
        $this->assertNull($itf->category);
        $this->assertNull($itf->city);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('search=open', $uri);
        $this->assertStringContainsString('tour=atp', $uri);
    }

    public function testTournamentByIdEncodesThePath(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('tournaments')['data'][0]);
        $t = $this->client($mock)->getTournament('atp-cincinnati-singles');

        $this->assertInstanceOf(Tournament::class, $t);
        $this->assertStringContainsString('/tournaments/atp-cincinnati-singles', (string) $mock->lastRequest()?->getUri());
    }

    public function testUsageDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('usage'));
        $usage = $this->client($mock)->getUsage();

        $this->assertInstanceOf(Usage::class, $usage);
        $this->assertSame('pro', $usage->tier);
        $this->assertSame('basic', $usage->base_tier, 'a temporary grant is active');
        $this->assertSame('2026-08-14T00:00:00Z', $usage->tier_expires_at);
        $this->assertSame(10000, $usage->limits['per_day']);
        $this->assertSame(8760, $usage->today['remaining_day']);
        $this->assertCount(2, $usage->history);
    }

    public function testMatchPricesDecodeBareTicks(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('match_prices'));
        $page = $this->client($mock)->listMatchPrices(22313, 3, 60);

        $this->assertCount(3, $page);
        $tick = $page[0];
        $this->assertInstanceOf(Price::class, $tick);
        $this->assertSame(0.725, $tick->mid);
        $this->assertFalse($tick->synthetic, 'a real top-of-book quote');
        $this->assertTrue($page[2]->synthetic, 'synthesised quotes are tagged, never disguised');
        $this->assertSame('prediction_market', $tick->price_source);

        // has_more here means the window was clipped — there is no offset
        $this->assertTrue($page->meta?->has_more);

        $uri = (string) $mock->lastRequest()?->getUri();
        $this->assertStringContainsString('/matches/22313/prices', $uri);
        $this->assertStringContainsString('limit=3', $uri);
        $this->assertStringContainsString('minutes=60', $uri);
    }

    public function testCreateWebhookPostsJsonAndSurfacesTheSecretOnce(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('webhook_created'), 201);
        $hook = $this->client($mock)->createWebhook('https://example.dev/hooks/tennis', ['score', 'break_point']);

        $this->assertInstanceOf(Webhook::class, $hook);
        $this->assertSame(3, $hook->id);
        $this->assertSame('whsec_test_fixture_not_a_real_secret', $hook->secret, 'shown exactly once — store it');

        $request = $mock->lastRequest();
        $this->assertSame('POST', $request?->getMethod());
        $this->assertSame('application/json', $request?->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['url' => 'https://example.dev/hooks/tennis', 'events' => ['score', 'break_point']],
            json_decode((string) $request?->getBody(), true),
        );
    }

    public function testListWebhooksNeverCarriesTheSecret(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('webhooks'));
        $page = $this->client($mock)->listWebhooks();

        $this->assertCount(2, $page);
        $this->assertNull($page[0]->secret);
        $this->assertSame(14, $page[1]->consecutive_failures);
        $this->assertSame('connect timeout', $page[1]->last_error);
    }

    public function testDeleteWebhookSendsDeleteAndConfirms(): void
    {
        $mock = (new MockClient())->queueJson(['deleted' => 1]);
        $ok = $this->client($mock)->deleteWebhook(3);

        $this->assertTrue($ok);
        $request = $mock->lastRequest();
        $this->assertSame('DELETE', $request?->getMethod());
        $this->assertStringContainsString('/webhooks/3', (string) $request?->getUri());
    }

    public function testWebhookLimitIsAConflict(): void
    {
        // Max 3 webhooks per key — the 409 maps onto its own type.
        $mock = (new MockClient())->queueJson(['error' => 'webhook_limit'], 409);

        try {
            $this->client($mock)->createWebhook('https://example.dev/hooks/fourth');
            $this->fail('expected Conflict');
        } catch (Conflict $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('webhook_limit', $e->errorCode());
        }
    }

    public function testCreateWebhookIsNeverRetriedOnServerError(): void
    {
        // A replayed POST could register twice; only one queued response —
        // reaching the ServerError proves no retry consumed a second one.
        $mock = (new MockClient())->queueJson(['error' => 'boom'], 500);
        $client = $this->client($mock, ['max_retries' => 3]);

        try {
            $client->createWebhook('https://example.dev/hooks/tennis');
            $this->fail('expected ServerError');
        } catch (\LiveTennisApi\Exception\ServerError) {
            $this->assertCount(1, $mock->requests, 'POST must not be retried');
        }
    }
}
