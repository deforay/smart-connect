<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unauthenticated liveness check, and the capability probe LIS clients use to
 * decide whether this deployment speaks v2 at all: a 404 here means fall back
 * to the legacy /api/* paths.
 *
 * Deliberately touches neither the database nor the Laminas container.
 */
final class HealthHandler
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return ApiResponse::success(
            $this->responseFactory->createResponse(),
            [
                'api_version' => 'v2',
                'timestamp' => gmdate('c'),
            ],
            'Smart Connect API is reachable'
        );
    }
}
