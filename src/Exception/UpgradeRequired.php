<?php

declare(strict_types=1);

namespace LiveTennisApi\Exception;

/**
 * 403 — the endpoint exists but your tier does not unlock it.
 *
 * This is NOT an authentication failure. The key is valid; the plan is too low.
 * `requiredTier` is the lowest tier that unlocks the endpoint, inferred by the
 * library from the request path, because the API returns only
 * `{"error": "upgrade_required"}`.
 */
class UpgradeRequired extends ApiStatusError
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
        private readonly ?string $requiredTier = null,
    ) {
        if ($requiredTier !== null) {
            $message = "{$message} — this endpoint requires the {$requiredTier} tier."
                . ' See https://livetennisapi.com/#pricing';
        }

        parent::__construct($message, $statusCode, $body, $headers, $requestUrl);
    }

    public function getRequiredTier(): ?string
    {
        return $this->requiredTier;
    }
}
