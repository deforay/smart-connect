<?php

namespace Api\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;

class HealthController extends AbstractRestfulController
{
    use JsonResponseTrait;

    /**
     * Lightweight endpoint that callers can use to confirm the API is up.
     */
    public function getList(): Response
    {
        return $this->jsonResponse([
            'status' => 'ok',
            'message' => 'Smart Connect API is reachable',
            'timestamp' => gmdate('c'),
        ]);
    }
}
