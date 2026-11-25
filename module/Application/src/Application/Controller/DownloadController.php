<?php

namespace Application\Controller;

use Application\Session\Container;
use Laminas\Http\Response;
use Laminas\Http\Response\Stream;
use Laminas\Mvc\Controller\AbstractActionController;

/**
 * Serves Excel exports and result PDFs from EXPORT_PATH.
 *
 * These files hold sample and patient data. They used to sit in
 * public/temporary, where anyone with a file name could fetch them. They now
 * live outside the web root and are reachable only here, by a logged-in user.
 *
 * Module::preSetter() already turns away a request without a session, and the
 * ACL decides which roles hold the `index` privilege. The session check below
 * repeats the first, so the files stay private even if this controller is ever
 * listed as public there by mistake.
 */
class DownloadController extends AbstractActionController
{
    private const CONTENT_TYPES = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'csv' => 'text/csv; charset=utf-8',
        'pdf' => 'application/pdf',
    ];

    public function __construct(private string $exportPath)
    {
    }

    public function indexAction()
    {
        $session = new Container('credo');
        if (empty($session->userId)) {
            return $this->refuse(Response::STATUS_CODE_401);
        }

        // Only the last path component is looked up, and never a dot file:
        // the app writes none, so a name starting with "." is not one of ours.
        $name = basename((string) $this->params()->fromRoute('file', ''));
        if ($name === '' || str_starts_with($name, '.')) {
            return $this->refuse(Response::STATUS_CODE_404);
        }

        // realpath() resolves symlinks, so a link inside EXPORT_PATH that
        // points elsewhere is refused too.
        $root = realpath($this->exportPath);
        $path = realpath($this->exportPath . DIRECTORY_SEPARATOR . $name);
        if (
            $root === false
            || $path === false
            || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)
            || !is_file($path)
        ) {
            return $this->refuse(Response::STATUS_CODE_404);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $this->refuse(Response::STATUS_CODE_404);
        }

        // Streamed rather than read into a string, so a large export does not
        // count against the memory limit.
        $response = new Stream();
        $response->setStream($handle);
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->setStreamName($name);

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', self::CONTENT_TYPES[$extension] ?? 'application/octet-stream');
        $headers->addHeaderLine('Content-Disposition', $this->attachment($name));
        $headers->addHeaderLine('Content-Length', (string) filesize($path));
        $headers->addHeaderLine('X-Content-Type-Options', 'nosniff');
        $headers->addHeaderLine('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * File names come from sample codes, so they are not trusted inside a
     * header: the plain filename keeps only safe ASCII, and filename* carries
     * the original for browsers that read it.
     */
    private function attachment(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

        return sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $ascii, rawurlencode($name));
    }

    private function refuse(int $status): Response
    {
        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->setContent('');

        return $response;
    }
}
