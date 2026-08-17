<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use App\Http\LegacyResult;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/v2/vl/weblims — replaces POST /api/weblims-vl.
 *
 * A raw JSON body rather than a file upload. The empty-body case is rejected
 * here because the service's own guard builds an error array and then carries
 * on parsing anyway.
 */
final class WeblimsVlHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LaminasBridge $bridge
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $payload = trim((string) $request->getBody());

        if ($payload === '' || $payload === '[]') {
            return ApiResponse::error($response, 'Missing data in API request', 422);
        }

        if (json_decode($payload) === null && json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error($response, 'Request body is not valid JSON: ' . json_last_error_msg(), 422);
        }

        return LegacyResult::toResponse(
            $response,
            $this->bridge->get('SampleService')->saveWeblimsVLAPI($payload),
            'Records received'
        );
    }
}
