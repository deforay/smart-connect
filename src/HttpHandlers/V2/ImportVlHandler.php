<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use App\Http\UploadGuard;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/v2/vl/import — replaces POST /api/import-viral-load.
 *
 * Parks a file in uploads/not-import-vl for offline processing; there is no
 * service behind it. v1 took whatever name the client sent, so the accepted
 * extensions are pinned here and the stored name is generated rather than
 * echoed back from the request.
 */
final class ImportVlHandler
{
    private const FIELD = 'vlFile';

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LaminasBridge $bridge
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        if (UploadGuard::postMaxSizeExceeded()) {
            return ApiResponse::error(
                $response,
                sprintf('Request body exceeds the server limit of %s', ini_get('post_max_size')),
                413
            );
        }

        $problem = UploadGuard::validate(self::FIELD);
        if ($problem !== null) {
            return ApiResponse::error($response, $problem, 422);
        }

        $directory = $this->bridge->uploadPath('not-import-vl');
        UploadGuard::ensureDirectory($directory);

        $extension = strtolower(pathinfo((string) $_FILES[self::FIELD]['name'], PATHINFO_EXTENSION));
        $storedName = sprintf('%s-%s.%s', date('YmdHis'), bin2hex(random_bytes(6)), $extension);
        $target = $directory . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string) $_FILES[self::FIELD]['tmp_name'], $target)) {
            return ApiResponse::error($response, 'Could not store the uploaded file', 500);
        }

        return ApiResponse::success($response, ['stored_as' => $storedName], 'File received', 201);
    }
}
