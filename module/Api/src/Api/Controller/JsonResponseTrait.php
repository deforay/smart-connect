<?php

namespace Api\Controller;

use Laminas\Http\Response;
use Laminas\Json\Json;

trait JsonResponseTrait
{
    protected function jsonResponse(array $payload, int $statusCode = Response::STATUS_CODE_200): Response
    {
        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->setContent(Json::encode($payload, false, ['prettyPrint' => false]));
        return $response;
    }
}
