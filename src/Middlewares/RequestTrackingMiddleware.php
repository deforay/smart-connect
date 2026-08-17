<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Services\EnrollmentService;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Records authenticated v2 calls in dash_track_api_requests and stamps
 * last_seen on the calling client.
 *
 * last_seen is what tells an administrator which labs have moved to v2 and
 * which are still on the legacy endpoints — the report behind
 * `bin/console api-usage`, which is in turn what makes the legacy cutoff date a
 * decision rather than a guess.
 *
 * Runs inside the auth middleware so the principal is known, and never fails
 * the request: tracking is diagnostics, not the job.
 */
final class RequestTrackingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LaminasBridge $bridge,
        private readonly EnrollmentService $enrollment
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        try {
            $principal = $request->getAttribute(BearerAuthMiddleware::ATTRIBUTE);

            if (is_array($principal) && ($principal['type'] ?? null) === 'client') {
                $this->enrollment->touch((int) $principal['client_id']);
            }

            $this->track($request, $response, is_array($principal) ? $principal : []);
        } catch (Throwable $e) {
            error_log('[api-v2] request tracking failed: ' . $e->getMessage());
        }

        return $response;
    }

    /** @param array<string, mixed> $principal */
    private function track(ServerRequestInterface $request, ResponseInterface $response, array $principal): void
    {
        $path = $request->getUri()->getPath();

        $trackerTable = $this->bridge->get('DashTrackApiRequestsTable');

        // Deliberately not the uploaded payload: these are multi-megabyte
        // sample dumps and addApiTracking() would zip a copy of every one to
        // disk. The form fields and the response envelope are what is worth
        // keeping.
        $requestSummary = [
            'method' => $request->getMethod(),
            'path' => $path,
            'params' => $this->formFields(),
            'files' => $this->uploadSummary(),
            'principal' => array_diff_key($principal, array_flip(['token'])),
        ];

        $trackerTable->addApiTracking(
            Uuid::uuid4()->toString(),
            $principal['label'] ?? 'api-v2-client',
            0,
            'api-v2' . str_replace('/', '-', substr($path, strlen('/api/v2'))),
            $this->testTypeFor($path),
            $path,
            $requestSummary,
            (string) $response->getBody(),
            'json',
            $principal['lab_id'] ?? null
        );
    }

    /** @return array<string, mixed> */
    private function formFields(): array
    {
        return array_diff_key($_POST, array_flip(['password', 'token', 'enrollment_key']));
    }

    /** @return array<string, array<string, mixed>> */
    private function uploadSummary(): array
    {
        $summary = [];
        foreach ($_FILES as $field => $file) {
            if (is_array($file)) {
                $summary[$field] = [
                    'name' => $file['name'] ?? null,
                    'size' => $file['size'] ?? null,
                ];
            }
        }

        return $summary;
    }

    private function testTypeFor(string $path): string
    {
        return match (true) {
            str_contains($path, '/eid') => 'eid',
            str_contains($path, '/covid19') => 'covid19',
            str_contains($path, '/vl') => 'vl',
            default => 'common',
        };
    }
}
