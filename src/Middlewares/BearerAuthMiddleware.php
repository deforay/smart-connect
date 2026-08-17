<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Http\ApiResponse;
use App\Services\EnrollmentService;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bearer authentication for /api/v2 — the thing v1 never had on its ingestion
 * endpoints, where anyone who knew the URL could POST into the dashboard.
 *
 * Two kinds of principal, because the API serves two audiences:
 *
 *  - `client` — an enrolled LIS pushing data up (dash_api_clients)
 *  - `user`   — a human-issued API account pulling data down
 *               (dash_users.api_token, role 6), as v1's /api/source-data used
 *
 * Which kinds a route accepts is fixed per route; presenting a valid token of
 * the wrong kind is 403, not 401, so an integrator can tell "you are not who
 * this endpoint is for" from "I do not know you".
 */
final class BearerAuthMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'api_principal';

    /** @param array<int, string> $accepts One or both of 'client', 'user'. */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly EnrollmentService $enrollment,
        private readonly LaminasBridge $bridge,
        private readonly array $accepts = ['client']
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return $this->unauthorized('Missing Bearer token');
        }

        $principal = $this->resolve($token);

        if ($principal === null) {
            return $this->unauthorized('Invalid or revoked token');
        }

        if (!in_array($principal['type'], $this->accepts, true)) {
            return ApiResponse::error(
                $this->responseFactory->createResponse(),
                sprintf('This endpoint requires a %s token', implode(' or ', $this->accepts)),
                403
            );
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $principal));
    }

    /** @return array<string, mixed>|null */
    private function resolve(string $token): ?array
    {
        $client = $this->enrollment->authenticate($token);
        if ($client !== null) {
            return [
                'type' => 'client',
                'client_id' => (int) $client['client_id'],
                'instance_uuid' => $client['instance_uuid'],
                'lab_id' => $client['lab_id'] === null ? null : (int) $client['lab_id'],
                'facility_code' => $client['facility_code'],
                'label' => $client['label'],
            ];
        }

        // Only look up an api user if the route would accept one — this is the
        // request that boots the Laminas container, so skip it where it cannot
        // change the outcome.
        if (!in_array('user', $this->accepts, true)) {
            return null;
        }

        $row = $this->bridge->adapter()->query(
            "SELECT user_id, user_name, email, api_token
             FROM dash_users
             WHERE api_token = ? AND role = 6 AND status = 'active'
             LIMIT 1",
            [$token]
        )->current();

        if (!$row) {
            return null;
        }

        $row = (array) $row;

        return [
            'type' => 'user',
            'user_id' => (int) $row['user_id'],
            'label' => $row['user_name'] ?? $row['email'],
            // Carried so handlers can hand it to services that re-check the
            // token themselves (SampleService::getSourceData).
            'token' => $row['api_token'],
        ];
    }

    private function bearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '' || !preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return ApiResponse::error(
            $this->responseFactory->createResponse()->withHeader('WWW-Authenticate', 'Bearer'),
            $message,
            401
        );
    }
}
