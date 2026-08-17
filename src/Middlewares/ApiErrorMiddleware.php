<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Http\ApiResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * Outermost middleware: nothing leaves /api/v2 as an HTML error page or a stack
 * trace. Unexpected failures get an error_id that is also written to the PHP
 * error log, so an integrator can quote it and the log line can be found.
 */
final class ApiErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $debug = false
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException) {
            return ApiResponse::error($this->responseFactory->createResponse(), 'Unknown endpoint', 404);
        } catch (HttpMethodNotAllowedException $e) {
            return ApiResponse::error(
                $this->responseFactory->createResponse()->withHeader('Allow', implode(', ', $e->getAllowedMethods())),
                'Method not allowed',
                405
            );
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(8));

            error_log(sprintf(
                '[api-v2] error_id=%s %s %s — %s in %s:%d',
                $errorId,
                $request->getMethod(),
                (string) $request->getUri()->getPath(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            error_log($e->getTraceAsString());

            return ApiResponse::error(
                $this->responseFactory->createResponse(),
                $this->debug ? $e->getMessage() : 'Internal server error',
                500,
                ['error_id' => $errorId]
            );
        }
    }
}
