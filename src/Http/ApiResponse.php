<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The single response envelope for /api/v2.
 *
 * Every response body is {"status": "success"|"error", "message": ..., "data": ...}
 * and — unlike the v1 API, which returned '403'/'422' strings inside HTTP 200 —
 * the HTTP status code carries the same verdict as the envelope.
 */
final class ApiResponse
{
    public static function success(
        ResponseInterface $response,
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $extra = []
    ): ResponseInterface {
        return self::write($response, $extra + [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(
        ResponseInterface $response,
        string $message,
        int $status = 400,
        array $extra = []
    ): ResponseInterface {
        return self::write($response, $extra + [
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ], $status);
    }

    /**
     * Write an already-shaped payload. Kept separate so callers that need an
     * unusual envelope (the sunset 410, for instance) do not have to hand-roll
     * the JSON encoding and header.
     */
    public static function write(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $response->getBody()->write($json === false ? '{"status":"error","message":"Response encoding failed","data":null}' : $json);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
