<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests\Support;

use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that replays queued responses and records every request, so
 * the whole suite runs with zero network. A queued item may be a
 * {@see ResponseInterface} or a {@see ClientExceptionInterface} to simulate a
 * transport failure.
 */
final class MockClient implements ClientInterface
{
    /** @var array<int, ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    /** @var array<int, RequestInterface> */
    public array $requests = [];

    /**
     * Queue a JSON response.
     *
     * @param array<string, string> $headers
     */
    public function queueJson(mixed $body, int $status = 200, array $headers = []): self
    {
        $this->queue[] = new Response(
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            json_encode($body),
        );

        return $this;
    }

    public function queueResponse(ResponseInterface|ClientExceptionInterface $item): self
    {
        $this->queue[] = $item;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new LogicException('MockClient: no queued response for ' . $request->getUri());
        }

        $next = array_shift($this->queue);
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): ?RequestInterface
    {
        return $this->requests[array_key_last($this->requests)] ?? null;
    }
}
