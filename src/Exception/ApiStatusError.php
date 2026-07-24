<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * The API returned a non-2xx response.
 *
 * Carries the HTTP status, the parsed body and the response headers so callers
 * can inspect the raw response, but the common cases are distinguishable by
 * type alone (see the subclasses).
 */
class ApiStatusError extends LiveTennisApiError
{
    /**
     * @param mixed                 $body
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly mixed $body = null,
        private readonly array $headers = [],
        private readonly ?string $requestUrl = null,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return mixed */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRequestUrl(): ?string
    {
        return $this->requestUrl;
    }

    /**
     * The API's machine-readable code, e.g. `upgrade_required`.
     */
    public function errorCode(): ?string
    {
        if (is_array($this->body) && isset($this->body['error']) && is_string($this->body['error'])) {
            return $this->body['error'];
        }

        return null;
    }
}
