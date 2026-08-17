<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

use App\Http\ApiResponse;
use App\Services\EnrollmentService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/v2/enroll — a LIS trades the deployment's enrollment key for its
 * own token.
 *
 * This is the only unauthenticated write in the API, and the only reason the
 * rest of it can require a Bearer token without anyone issuing tokens by hand.
 * A country has hundreds of LIS installations; every one of them enrolls itself
 * on its first sync, using a key its installer already shipped.
 *
 * Calling it again reissues. That is not a loophole to close — it is how a LIS
 * recovers from a reinstall or a restored backup without a support ticket. The
 * holder of the enrollment key can already enroll, so being able to re-enroll
 * grants nothing new.
 */
final class EnrollHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly EnrollmentService $enrollment
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $body = $this->body($request);

        if (!$this->enrollment->enrollmentEnabled()) {
            return ApiResponse::error($response, 'Enrollment is not enabled on this deployment', 403);
        }

        $key = trim((string) ($body['enrollment_key'] ?? ''));
        $instanceUuid = trim((string) ($body['instance_uuid'] ?? ''));

        if ($instanceUuid === '') {
            return ApiResponse::error($response, 'instance_uuid is required', 422);
        }

        if (!$this->enrollment->keyMatches($key)) {
            return ApiResponse::error($response, 'Invalid enrollment key', 401);
        }

        $labId = $body['lab_id'] ?? null;

        $result = $this->enrollment->enroll(
            $instanceUuid,
            is_numeric($labId) ? (int) $labId : null,
            $this->optional($body['facility_code'] ?? null),
            $this->optional($body['label'] ?? null),
            $this->clientIp($request)
        );

        return ApiResponse::success(
            $response,
            [
                'token' => $result['token'],
                'client_id' => $result['client_id'],
                'instance_uuid' => $result['instance_uuid'],
                'status' => 'active',
            ],
            'Enrolled. Store this token — it is not retrievable again.',
            201
        );
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    private function optional(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $server = $request->getServerParams();

        return isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : null;
    }
}
