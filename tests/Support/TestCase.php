<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests\Support;

use LiveTennisApi\LiveTennisApi;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A client wired to the given mock transport, with retries sped up to zero
     * wait so retry paths are testable without real time passing.
     *
     * @param array<string, mixed> $options
     */
    protected function client(MockClient $mock, array $options = []): LiveTennisApi
    {
        return new LiveTennisApi('twjp_test', [
            'http_client' => $mock,
            'sleeper' => static fn (float $s): null => null,
        ] + $options);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        $path = __DIR__ . '/../fixtures/' . $name . '.json';
        $data = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($data, "fixture {$name} must decode to an array");

        return $data;
    }
}
