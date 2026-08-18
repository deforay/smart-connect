<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Api\CallContext;
use App\Services\EnrollmentService;
use App\Services\LaminasBridge;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Records every v2 call in dash_track_api_requests and stamps last_seen on the
 * calling client.
 *
 * last_seen is what tells an administrator which labs have moved to v2 and
 * which are still on the legacy endpoints — the report behind
 * `bin/console api-usage`, which is in turn what makes the legacy cutoff date a
 * decision rather than a guess.
 *
 * Runs outermost, wrapping ApiErrorMiddleware. That is deliberate: attached per
 * route and inside the auth middleware, as it was, it only ever saw calls that
 * had already succeeded at getting in. A rejected token, an unknown endpoint and
 * a handler that threw all went unrecorded, which is exactly the set of calls
 * someone goes looking for. From out here the response has already been shaped
 * by ApiErrorMiddleware, so its status and envelope describe what the caller
 * actually received.
 *
 * Never fails the request: tracking is diagnostics, not the job.
 */
final class RequestTrackingMiddleware implements MiddlewareInterface
{
    /**
     * The capability probe LIS clients call to choose between v2 and the legacy
     * paths. Recording it would bury real traffic under health checks.
     */
    private const UNTRACKED = ['/api/v2/health'];

    public function __construct(
        private readonly LaminasBridge $bridge,
        private readonly EnrollmentService $enrollment,
        private readonly CallContext $context
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = microtime(true);
        $response = $handler->handle($request);

        try {
            if (!$this->tracks($request)) {
                return $response;
            }

            $principal = $this->context->principal();

            if (($principal['type'] ?? null) === 'client') {
                $this->enrollment->touch((int) $principal['client_id']);
            }

            $this->track($request, $response, $principal, (int) round((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $e) {
            error_log('[api-v2] request tracking failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function tracks(ServerRequestInterface $request): bool
    {
        return !in_array($request->getUri()->getPath(), self::UNTRACKED, true);
    }

    /** @param array<string, mixed> $principal */
    private function track(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $principal,
        int $durationMs
    ): void {
        $path = $request->getUri()->getPath();
        $status = $response->getStatusCode();
        $envelope = $this->envelope($response);

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
            $principal['lab_id'] ?? null,
            [
                'http_status' => $status,
                'outcome' => $status >= 400 || ($envelope['status'] ?? null) === 'error' ? 'failed' : 'success',
                'error_message' => $status >= 400 ? ($envelope['message'] ?? null) : null,
                'error_id' => $envelope['error_id'] ?? null,
                'duration_ms' => $durationMs,
            ]
        );
    }

    /**
     * The response body as an array, or an empty array when it is not the
     * standard envelope. Every /api/v2 response is written by ApiResponse, so
     * this is normally just a decode.
     *
     * @return array<string, mixed>
     */
    private function envelope(ResponseInterface $response): array
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : [];
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
