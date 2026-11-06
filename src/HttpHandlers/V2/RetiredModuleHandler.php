<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Answers ingestion endpoints for modules this dashboard no longer carries
 * (POST /api/v2/covid19) with 410 Gone.
 *
 * LIS instances that still have the module switched on keep pushing on their
 * cron schedule. A non-success reply leaves their sync watermark where it is,
 * so if the module comes back, they resend everything from that point.
 */
final class RetiredModuleHandler
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return ApiResponse::error(
            $this->responseFactory->createResponse(),
            'This dashboard no longer accepts data for this module',
            410
        );
    }
}
