<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Translates the return values of the existing Laminas services into the v2
 * envelope.
 *
 * The services predate any convention: they variously return
 * ['status' => 'success'|'partial'|'failed'|'error'|'fail'] and
 * ['status' => '200'|'403'|'422'] with the human message under 'message' or
 * 'result'. Rather than rewrite the service layer to ship v2, that vocabulary
 * is normalised here — one place, easy to delete once the services themselves
 * are migrated.
 */
final class LegacyResult
{
    /** Legacy string status => HTTP status code. */
    private const HTTP_STATUS = [
        'success' => 200,
        'partial' => 200,
        'ok' => 200,
        'failed' => 500,
        'fail' => 500,
        'error' => 500,
    ];

    public static function toResponse(ResponseInterface $response, mixed $result, ?string $successMessage = null): ResponseInterface
    {
        if (!is_array($result)) {
            return ApiResponse::error($response, 'Upstream service returned no result', 500);
        }

        $rawStatus = strtolower(trim((string) ($result['status'] ?? '')));
        $message = self::message($result);
        $data = self::data($result);

        // Numeric statuses ('200', '403', '422') were HTTP codes smuggled into
        // the body. They are already the code we want.
        if ($rawStatus !== '' && ctype_digit($rawStatus)) {
            $code = (int) $rawStatus;
            // v1 answered '403' for bad credentials, which is really a 401.
            if ($code === 403 && $message !== null && stripos($message, 'invalid') !== false) {
                $code = 401;
            }

            return $code >= 400
                ? ApiResponse::error($response, $message ?? 'Request failed', $code)
                : ApiResponse::success($response, $data, $message ?? $successMessage, $code);
        }

        $code = self::HTTP_STATUS[$rawStatus] ?? 500;

        if ($code >= 400) {
            return ApiResponse::error($response, $message ?? 'Request failed', $code);
        }

        return ApiResponse::success(
            $response,
            $data,
            $message ?? $successMessage,
            $code,
            $rawStatus === 'partial' ? ['partial' => true] : []
        );
    }

    private static function message(array $result): ?string
    {
        foreach (['message', 'msg'] as $key) {
            if (isset($result[$key]) && is_scalar($result[$key]) && trim((string) $result[$key]) !== '') {
                return (string) $result[$key];
            }
        }

        // 'result' doubles as the message on failures and as the payload on success.
        if (isset($result['result']) && is_string($result['result']) && trim($result['result']) !== '') {
            return $result['result'];
        }

        return null;
    }

    private static function data(array $result): mixed
    {
        if (array_key_exists('data', $result)) {
            return $result['data'];
        }
        if (isset($result['result']) && !is_string($result['result'])) {
            return $result['result'];
        }
        if (isset($result['token'])) {
            return ['token' => $result['token']];
        }

        // Anything the service returned beyond the status/message vocabulary.
        $rest = array_diff_key($result, array_flip(['status', 'message', 'msg', 'result']));

        return $rest === [] ? null : $rest;
    }
}
