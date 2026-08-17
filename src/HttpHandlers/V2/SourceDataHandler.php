<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\LegacyResult;
use App\Middlewares\BearerAuthMiddleware;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/v2/vl/source-data — replaces POST /api/source-data.
 *
 * v1 read the api token out of the request body; v2 takes it from the
 * Authorization header like every other endpoint. The token is still handed to
 * the service, which re-checks it — belt and braces, and it means
 * fetchSourceData() is untouched.
 */
final class SourceDataHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LaminasBridge $bridge
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $parsed = $request->getParsedBody();
        $params = is_array($parsed) ? $parsed : [];

        /** @var array<string, mixed> $principal */
        $principal = $request->getAttribute(BearerAuthMiddleware::ATTRIBUTE, []);
        $params['token'] = $principal['token'] ?? '';

        return LegacyResult::toResponse(
            $this->responseFactory->createResponse(),
            $this->bridge->get('SampleService')->getSourceData($params)
        );
    }
}
