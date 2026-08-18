<?php

declare(strict_types=1);

namespace App\Api;

/**
 * What one /api/v2 call knows about itself, outside the PSR-7 request.
 *
 * RequestTrackingMiddleware runs outermost, so it sees every response including
 * the ones auth and error handling produce. That position costs it the request
 * attributes, because a PSR-7 request is immutable and an attribute set by an
 * inner middleware never travels back out. This object is where an inner
 * middleware leaves anything the tracker needs.
 *
 * One instance per request: the DI container is built per request in
 * src/Api/bootstrap.php, so there is nothing to reset between calls.
 */
final class CallContext
{
    /** @var array<string, mixed> */
    private array $principal = [];

    /** @param array<string, mixed> $principal */
    public function setPrincipal(array $principal): void
    {
        $this->principal = $principal;
    }

    /** @return array<string, mixed> */
    public function principal(): array
    {
        return $this->principal;
    }
}
