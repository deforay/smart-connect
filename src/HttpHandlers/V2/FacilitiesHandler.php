<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/v2/facilities — replaces POST /api/facility.
 *
 * Returns the facility list. The service takes no arguments; v1's controller
 * passed request params it ignored.
 */
final class FacilitiesHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LaminasBridge $bridge
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $facilities = $this->bridge->get('FacilityService')->getAllFacilitiesInApi();

        return ApiResponse::success(
            $this->responseFactory->createResponse(),
            $facilities,
            sprintf('%d facilities', is_countable($facilities) ? count($facilities) : 0)
        );
    }
}
