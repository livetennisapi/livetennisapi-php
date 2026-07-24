<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 429 — the tier's rate-limit window was exceeded.
 *
 * `retryAfter` is the number of seconds the API asked you to wait, parsed from
 * the `Retry-After` header. It is `null` when the header is absent or
 * unparseable.
 */
class RateLimited extends ApiStatusError
{
    /**
     * @param mixed                 $body
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        int $statusCode,
        mixed $body = null,
        array $headers = [],
        ?string $requestUrl = null,
        private readonly ?float $retryAfter = null,
    ) {
        if ($retryAfter !== null) {
            $message = "{$message} — retry after " . rtrim(rtrim(sprintf('%.3f', $retryAfter), '0'), '.') . 's';
        }

        parent::__construct($message, $statusCode, $body, $headers, $requestUrl);
    }

    public function getRetryAfter(): ?float
    {
        return $this->retryAfter;
    }
}
