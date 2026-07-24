<?php

declare(strict_types=1);

namespace LiveTennisApi\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * A PSR-18 network exception double, so transport failures (connection dropped,
 * timed out) can be simulated without real sockets.
 */
final class NetworkFailure extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
