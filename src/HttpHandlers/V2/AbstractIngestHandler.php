<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use App\Http\LegacyResult;
use App\Http\UploadGuard;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared shape of the three file-ingestion endpoints (VL, EID, Covid-19).
 *
 * Each is a thin wrapper over the service method its v1 controller already
 * called. What v2 adds is everything that happens before that call: the upload
 * is checked, the temp directory is created with sane permissions rather than
 * 0777, and a bad request is a 422 with a reason instead of an undefined-index
 * fatal.
 *
 * The `api-version` body parameter is gone. v2 always takes the V2 code path;
 * v1's saveFileFromVlsmAPIV1() stays reachable only through the legacy routes,
 * and dies with them.
 */
abstract class AbstractIngestHandler
{
    public function __construct(
        protected readonly ResponseFactoryInterface $responseFactory,
        protected readonly LaminasBridge $bridge
    ) {
    }

    /** The multipart field name the LIS posts the file under. */
    abstract protected function fileField(): string;

    /** Sub-directory of TEMP_UPLOAD_PATH the service unpacks into. */
    abstract protected function tempFolder(): string;

    /** Invoke the existing service method and return its legacy result array. */
    abstract protected function ingest(): mixed;

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        if (UploadGuard::postMaxSizeExceeded()) {
            return ApiResponse::error(
                $response,
                sprintf(
                    'Request body exceeds the server limit of %s; send fewer records per sync',
                    ini_get('post_max_size')
                ),
                413
            );
        }

        $problem = UploadGuard::validate($this->fileField());
        if ($problem !== null) {
            return ApiResponse::error($response, $problem, 422);
        }

        // Created here so the service's own `mkdir(..., 0777, true)` branch is
        // never reached.
        UploadGuard::ensureDirectory($this->bridge->tempUploadPath($this->tempFolder()));

        return LegacyResult::toResponse($response, $this->ingest(), 'Records received');
    }
}
