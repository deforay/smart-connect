<?php

namespace Application\Controller;

use Laminas\Http\Response\Stream;
use Laminas\Mvc\Controller\AbstractActionController;
use Application\Session\Container;

/**
 * Serves the Excel exports and result PDFs the dashboards generate.
 *
 * They are written to EXPORT_PATH, outside public/, because they hold sample
 * and patient data. They used to sit in public/temporary, where anyone who had
 * a file name could fetch one without logging in.
 */
class DownloadController extends AbstractActionController
{
    private const CONTENT_TYPES = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'csv' => 'text/csv; charset=utf-8',
        'pdf' => 'application/pdf',
    ];

    public function indexAction()
    {
        // Module::preSetter() already turns away a request without a session.
        // Checked again here so this action never depends on that list.
        $session = new Container('credo');
        if (empty($session->userId)) {
            return $this->refuse(401);
        }

        // basename() drops any directory part, so the name cannot climb out of
        // EXPORT_PATH. A dot-file is never an export.
        $name = basename((string) $this->params()->fromRoute('file', ''));
        if ($name === '' || str_starts_with($name, '.')) {
            return $this->refuse(400);
        }

        $path = EXPORT_PATH . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            return $this->refuse(404);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';

        // Quotes and backslashes would end the quoted filename early.
        $quotedName = str_replace(['"', '\\'], '_', $name);

        $response = new Stream();
        $response->setStream(fopen($path, 'rb'));
        $response->setStatusCode(200);
        $response->setStreamName($name);

        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', $contentType);
        $headers->addHeaderLine(
            'Content-Disposition',
            'attachment; filename="' . $quotedName . '"; filename*=UTF-8\'\'' . rawurlencode($name)
        );
        $headers->addHeaderLine('Content-Length', (string) filesize($path));
        $headers->addHeaderLine('X-Content-Type-Options', 'nosniff');
        $headers->addHeaderLine('Cache-Control', 'private, no-store');

        return $response;
    }

    private function refuse(int $status)
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->setContent('');

        return $response;
    }
}
