<?php

namespace AlphaDirect\Services\Mapfre;

/**
 * MapfreResponse — immutable result of a MAPFRE / MAWDY "maip-travel" API call.
 *
 * Mirrors the SwiftlyResponse / DpoResponse pattern: callers branch on
 * isSuccess() and read the decoded body via data(). The raw body excerpt is
 * kept short for safe logging — NEVER log eMiA / Cognito tokens, the password,
 * or client_secret.
 */
class MapfreResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?int $httpStatus,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly ?string $rawExcerpt = null,
    ) {
    }

    public static function success(int $httpStatus, array $data, ?string $rawExcerpt = null): self
    {
        return new self(true, $httpStatus, $data, null, $rawExcerpt);
    }

    public static function failure(?int $httpStatus, string $error, ?string $rawExcerpt = null): self
    {
        return new self(false, $httpStatus, [], $error, $rawExcerpt);
    }

    public function isSuccess(): bool
    {
        return $this->ok;
    }

    /** @return array decoded JSON body (empty on failure) */
    public function data(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
