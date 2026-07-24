<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests;

use LiveTennisApi\Model\Page;
use LiveTennisApi\Model\Player;
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
}
