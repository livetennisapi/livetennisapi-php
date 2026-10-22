<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests;

use LiveTennisApi\Exception\UpgradeRequired;
use LiveTennisApi\Model\Player;
use LiveTennisApi\Model\PlayerStats;
use LiveTennisApi\Tests\Support\MockClient;
use LiveTennisApi\Tests\Support\TestCase;

/**
 * GET /players/{id}/stats and the two profile fields /players/{id} gained on
 * 2026-10-22.
 */
final class PlayerStatsTest extends TestCase
{
    public function testFiltersReachTheWire(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('player_stats'));
        $this->client($mock)->getPlayerStats(810, '2025-01-01', '2025-12-31', 'clay', 'atp', 5);

        $uri = $mock->lastRequest()?->getUri();
        $this->assertStringEndsWith('/players/810/stats', (string) $uri?->getPath());
        $this->assertSame('from=2025-01-01&to=2025-12-31&surface=clay&level=atp&last=5', $uri?->getQuery());
    }

    public function testNoFiltersSendsNoQuery(): void
    {
        $mock = (new MockClient())->queueJson(['player' => ['id' => 1]]);
        $stats = $this->client($mock)->getPlayerStats(1);

        $this->assertSame('', $mock->lastRequest()?->getUri()->getQuery());
        $this->assertInstanceOf(PlayerStats::class, $stats);
        $this->assertNull($stats->serve_return, 'absent below ULTRA stays null');
    }

    public function testDecodes(): void
    {
        $mock = (new MockClient())->queueJson($this->fixture('player_stats'));
        $stats = $this->client($mock)->getPlayerStats(810);

        $this->assertSame(810, $stats->player['id']);
        $this->assertSame(2, $stats->filters['last']);
        $records = $stats->records;
        $this->assertSame(28, $records['overall']['wins']);
        $this->assertSame(73.3, $records['by_surface']['hard']['win_pct']);
        $this->assertNull($records['by_surface']['grass']['win_pct'], 'an empty sample is null, never 0');
        $this->assertSame(1, $records['sets']['sample']['excluded']['walkover']);
        $this->assertSame(9, $records['sets']['tiebreaks']['won']);
        $this->assertSame(18, $records['sets']['comebacks']['matches_lost_a_set']);
        $this->assertSame(1, $records['withdrawals']['walkover']['received']);
        $this->assertCount(2, $records['recent']);
        $this->assertSame([2, 1], $records['recent'][0]['score']['sets']);
        $this->assertNull($records['recent'][1]['score']);

        $sr = $stats->serve_return;
        $this->assertSame(35, $sr['matches_with_stats']);
        $this->assertSame(1500, $sr['serve']['first_serve_in']['numerator']);
        $this->assertNull($sr['serve']['double_faults']['total']);
        $this->assertSame(0, $sr['serve']['double_faults']['matches']);
        $this->assertNull($sr['return']['break_points_converted']['pct']);
    }

    public function test403InfersPro(): void
    {
        $mock = (new MockClient())->queueJson(['error' => 'upgrade_required'], 403);

        try {
            $this->client($mock)->getPlayerStats(1);
            $this->fail('expected UpgradeRequired');
        } catch (UpgradeRequired $e) {
            $this->assertSame('PRO', $e->getRequiredTier());
        }
    }

    public function testPlayerHeightAndDoublesRanking(): void
    {
        $mock = (new MockClient())->queueJson([
            'id' => 1,
            'height_cm' => 188,
            'doubles_ranking' => ['rank' => 42, 'points' => 1200, 'observed_on' => '2026-10-19'],
        ]);
        $player = $this->client($mock)->getPlayer(1);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame(188, $player->height_cm);
        $this->assertSame(42, $player->doubles_ranking['rank']);
        $this->assertSame('2026-10-19', $player->doubles_ranking['observed_on']);
    }

    public function testPlayerFreeKeyNullsStayNull(): void
    {
        $mock = (new MockClient())->queueJson(['id' => 1, 'height_cm' => null, 'doubles_ranking' => null]);
        $player = $this->client($mock)->getPlayer(1);

        $this->assertNull($player->height_cm);
        $this->assertNull($player->doubles_ranking);
    }
}
