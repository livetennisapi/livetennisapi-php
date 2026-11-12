<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests;

use LiveTennisApi\Model\Fixture;
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
        // is_qualifying is three-valued: absence stays null — never false.
        $this->assertNull($match->is_qualifying);
        // pregame_forecasts is absent below ULTRA: null — never a guessed [].
        $this->assertNull($match->pregame_forecasts);
    }

    public function testMatchQualifyingAndPregameForecasts(): void
    {
        $forecast = ['win_probability_p1' => 0.58, 'stage' => 'pregame'];
        $match = TennisMatch::fromArray([
            'id' => 1,
            'is_qualifying' => true,
            'pregame_forecasts' => [$forecast],
        ]);
        $this->assertInstanceOf(TennisMatch::class, $match);
        $this->assertTrue($match->is_qualifying);
        $this->assertSame([$forecast], $match->pregame_forecasts);

        // False and null are different answers, and an empty list stays a list.
        $main = TennisMatch::fromArray(['id' => 2, 'is_qualifying' => false, 'pregame_forecasts' => []]);
        $this->assertInstanceOf(TennisMatch::class, $main);
        $this->assertFalse($main->is_qualifying);
        $this->assertSame([], $main->pregame_forecasts);

        $unstated = TennisMatch::fromArray(['id' => 3, 'is_qualifying' => null]);
        $this->assertInstanceOf(TennisMatch::class, $unstated);
        $this->assertNull($unstated->is_qualifying);
    }

    public function testFixtureFieldsAreTyped(): void
    {
        $forecast = ['win_probability_p1' => 0.61, 'stage' => 'pregame', 'created_at' => '2026-09-24T07:00:00Z'];
        $fixture = Fixture::fromArray([
            'id' => 87,
            'event_date' => '2026-09-25',
            'start_time' => '2026-09-25T11:00:00Z',
            'player1_id' => 925,
            'player2_id' => null,
            'tour' => 'wta',
            'tournament' => 'Rome',
            'round' => 'Quarter-finals',
            'round_code' => 'QF',
            'surface' => 'clay',
            'player1_name' => 'M. Kessler',
            'player2_name' => 'I. Jovic',
            'status' => 'cancelled',
            'reason' => 'walkover',
            'match_id' => 48213,
            'gender' => 'women',
            'is_qualifying' => false,
            'created_at' => '2026-09-23T08:00:00Z',
            'updated_at' => '2026-09-24T10:30:00Z',
            'pregame_forecasts' => [$forecast],
        ]);
        $this->assertInstanceOf(Fixture::class, $fixture);
        $this->assertSame('2026-09-25T11:00:00Z', $fixture->start_time);
        $this->assertSame(925, $fixture->player1_id);
        $this->assertNull($fixture->player2_id);
        $this->assertSame('QF', $fixture->round_code);
        $this->assertSame('walkover', $fixture->reason);
        $this->assertSame(48213, $fixture->match_id);
        $this->assertSame('women', $fixture->gender);
        $this->assertFalse($fixture->is_qualifying);
        $this->assertSame('2026-09-23T08:00:00Z', $fixture->created_at);
        $this->assertSame('2026-09-24T10:30:00Z', $fixture->updated_at);
        $this->assertSame([$forecast], $fixture->pregame_forecasts);
    }

    public function testFixtureNullAndAbsentFieldsStayNull(): void
    {
        // Explicit nulls: no match yet, a mixed event, a draw never stated.
        $nulls = Fixture::fromArray([
            'id' => 88,
            'start_time' => null,
            'reason' => null,
            'match_id' => null,
            'gender' => null,
            'is_qualifying' => null,
            'created_at' => null,
            'updated_at' => null,
            'pregame_forecasts' => [],
        ]);
        $this->assertInstanceOf(Fixture::class, $nulls);
        $this->assertNull($nulls->start_time);
        $this->assertNull($nulls->reason);
        $this->assertNull($nulls->match_id);
        $this->assertNull($nulls->gender);
        // Null means never stated, and null is not false.
        $this->assertNull($nulls->is_qualifying);
        $this->assertNull($nulls->created_at);
        $this->assertNull($nulls->updated_at);
        // An empty list is "no forecast yet" and stays a list.
        $this->assertSame([], $nulls->pregame_forecasts);

        // Below ULTRA the field is absent: null — never a guessed [].
        $lower = Fixture::fromArray(['id' => 89]);
        $this->assertInstanceOf(Fixture::class, $lower);
        $this->assertNull($lower->pregame_forecasts);
        $this->assertNull($lower->is_qualifying);
        $this->assertNull($lower->match_id);
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
