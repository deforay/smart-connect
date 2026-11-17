<?php

namespace Application\Controller;

use Application\Session\Container;
use Laminas\Http\Response\Stream;
use Laminas\Mvc\Controller\AbstractActionController;

/**
 * Serves the Excel exports and result PDFs the dashboards generate.
 *
 * They used to be written to public/temporary and opened straight from there,
 * so anyone holding a file name could fetch sample and patient data without
 * logging in. They now live in EXPORT_PATH, outside the web root, and this is
 * the only way to reach them.
 *
 * Module::preSetter() already turns away a request without a session and
 * checks the ACL. The session check is repeated here so that a change to that
 * listener cannot open this endpoint by accident.
 */
class DownloadController extends AbstractActionController
{
    private const CONTENT_TYPES = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'pdf' => 'application/pdf',
        'csv' => 'text/csv; charset=utf-8',
    ];

    public function indexAction()
    {
        $session = new Container('credo');
        if (empty($session->userId)) {
            return $this->refuse(401);
        }

        // basename() drops any directory part, so the name cannot climb out
        // of EXPORT_PATH. A name that is still empty or starts with '..' is
        // refused rather than guessed at.
        $name = basename((string) $this->params()->fromRoute('file', ''));
        if ($name === '' || str_starts_with($name, '..')) {
            return $this->refuse(404);
        }

        $path = EXPORT_PATH . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            return $this->refuse(404);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';

        // Streamed rather than read into a string, so a large export does not
        // count against the memory limit.
        $response = new Stream();
        $response->setStream(fopen($path, 'rb'));
        $response->setStatusCode(200);
        $response->setStreamName($name);

        $quoted = str_replace(['"', '\\'], '_', $name);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', $contentType);
        $headers->addHeaderLine(
            'Content-Disposition',
            'attachment; filename="' . $quoted . '"; filename*=UTF-8\'\'' . rawurlencode($name)
        );
        $headers->addHeaderLine('Content-Length', (string) filesize($path));
        $headers->addHeaderLine('X-Content-Type-Options', 'nosniff');
        $headers->addHeaderLine('Cache-Control', 'private, no-store');

        return $response;
    }

    private function refuse(int $status)
    {
        /** @var \Laminas\Http\Response $response */
        $response = $this->getResponse();
        $response->setStatusCode($status);

        return $response;
    }
}
