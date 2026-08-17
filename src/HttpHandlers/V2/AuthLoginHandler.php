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
 * POST /api/v2/auth/login — replaces POST /api/user.
 *
 * Unchanged flow (UsersTable::userLoginDetailsApi issues or returns the role-6
 * api_token); what changes is that bad credentials now answer 401 rather than
 * the string '403' inside an HTTP 200.
 *
 * This is for the human-issued API accounts that read data out. A LIS pushing
 * data in wants /api/v2/enroll instead.
 */
final class AuthLoginHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LaminasBridge $bridge
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $parsed = $request->getParsedBody();
        $body = is_array($parsed) ? $parsed : [];

        $userName = trim((string) ($body['userName'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($userName === '' || $password === '') {
            return ApiResponse::error($response, 'userName and password are required', 422);
        }

        $result = $this->bridge->get('UserService')->userLoginApi([
            'userName' => $userName,
            'password' => $password,
        ]);

        return LegacyResult::toResponse($response, $result, 'Authenticated');
    }
}
