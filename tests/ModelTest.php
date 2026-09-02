<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests;

use LiveTennisApi\Model\HistoryTapeRow;
use LiveTennisApi\Model\ListMeta;
use LiveTennisApi\Model\Page;
use LiveTennisApi\Model\Player;
use LiveTennisApi\Model\RankingRecord;
use LiveTennisApi\Model\Score;
use LiveTennisApi\Model\TennisMatch;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testNullInNullOut(): void
    {
        $this->assertNull(Score::fromArray(null));
        $this->assertNull(TennisMatch::fromArray(null));
    }

    public function testUnknownFieldPreservedAndReadable(): void
    {
        // Additive-change contract: an undeclared field is kept and readable.
        $player = Player::fromArray(['id' => 1, 'name' => 'X', 'brand_new_field' => 42]);
        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame(42, $player->brand_new_field);
        $this->assertSame(42, $player->raw['brand_new_field']);
        $this->assertNull($player->nonexistent);
    }

    public function testWronglyTypedFieldIsNotFatalAndKeptInRaw(): void
    {
        // Server sends a string where an int is declared: must not fatal.
        $player = Player::fromArray(['id' => 'not-an-int', 'name' => 'Y']);
        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame('Y', $player->name);
        // declared property stayed at its default; the truth is preserved in raw
        $this->assertNull($player->id);
        $this->assertSame('not-an-int', $player->raw['id']);
    }

    public function testGamesForSetIsPlayerMajor(): void
    {
        $score = Score::fromArray([
            'sets' => [1, 1],
            'games' => [[6, 3, 2], [4, 6, 1]],
            'points' => ['40', 'AD'],
            'server' => 2,
        ]);
        $this->assertInstanceOf(Score::class, $score);
        $this->assertSame([6, 4], $score->gamesForSet(0));
        $this->assertSame([3, 6], $score->gamesForSet(1));
        $this->assertSame([2, 1], $score->gamesForSet(2));
        $this->assertSame([null, null], $score->gamesForSet(9));
        $this->assertSame(['40', 'AD'], $score->points);
    }

    public function testPageIsIterableCountableIndexable(): void
    {
        $page = new Page([Player::fromArray(['id' => 1]), Player::fromArray(['id' => 2])]);
        $this->assertCount(2, $page);
        $this->assertSame(1, $page[0]->id);

        $ids = [];
        foreach ($page as $p) {
            $ids[] = $p->id;
        }
        $this->assertSame([1, 2], $ids);
    }

    public function testToArrayReturnsExactPayload(): void
    {
        $payload = ['id' => 7, 'name' => 'Z', 'extra' => ['a' => 1]];
        $player = Player::fromArray($payload);
        $this->assertSame($payload, $player->toArray());
        $this->assertSame($payload, $player->jsonSerialize());
    }

    public function testListMetaTotalNullableAndHasMore(): void
    {
        // total is null when it cannot be counted cheaply (status=completed)
        $meta = ListMeta::fromArray(['limit' => 50, 'offset' => 0, 'count' => 50, 'total' => null, 'has_more' => true]);
        $this->assertNull($meta->total);
        $this->assertTrue($meta->has_more);

        $counted = ListMeta::fromArray(['limit' => 50, 'offset' => 200, 'count' => 14, 'total' => 214, 'has_more' => false]);
        $this->assertSame(214, $counted->total);
        $this->assertFalse($counted->has_more);
    }

    public function testMatchNewFieldsDefaultToNull(): void
    {
        // A pre-1.1 payload without the new fields still decodes cleanly.
        $match = TennisMatch::fromArray(['id' => 1, 'tournament' => 'X']);
        $this->assertInstanceOf(TennisMatch::class, $match);
        $this->assertNull($match->tour);
        $this->assertNull($match->tournament_id);
        $this->assertNull($match->round_code);
        $this->assertNull($match->withdrew);
        $this->assertNull($match->tape);
        // has_analysis / has_market (2026-09-02): an older server omits them,
        // and absence must stay null — never become false.
        $this->assertNull($match->has_analysis);
        $this->assertNull($match->has_market);
    }

    public function testReconstructedTapeRowIsNullTimestampAndNullModelFields(): void
    {
        // Reconstructed rows: null timestamp AND null model fields — nothing
        // synthesised. The null timestamp is the reliable row-level marker.
        $row = HistoryTapeRow::fromArray([
            'sets' => [0, 0],
            'games' => [[3], [2]],
            'points' => ['30', '15'],
            'server' => 1,
            'is_tiebreak' => false,
            'win_probability_p1' => null,
            'danger' => null,
            'timestamp' => null,
            'point_winner' => 1,
        ]);
        $this->assertInstanceOf(HistoryTapeRow::class, $row);
        $this->assertNull($row->timestamp);
        $this->assertNull($row->win_probability_p1);
        $this->assertSame(1, $row->point_winner);
    }

    public function testUtrRankingRecordHasRatingNotRank(): void
    {
        $record = RankingRecord::fromArray([
            'player_id' => 3819,
            'system' => 'utr',
            'rank' => null,
            'points' => null,
            'previous_rank' => null,
            'rating' => 16.21,
            'effective_date' => '2026-08-03',
        ]);
        $this->assertInstanceOf(RankingRecord::class, $record);
        $this->assertNull($record->rank, 'UTR is a rating, not a ranking');
        $this->assertNull($record->previous_rank);
        $this->assertSame(16.21, $record->rating);
    }
}
