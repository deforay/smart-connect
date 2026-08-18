<?php

namespace Application\Controller;

use App\Log\LogFileReader;
use Laminas\Http\Response\Stream;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Reads the application log from the browser.
 *
 * An install is one of hundreds and its operator has no shell on the server, so
 * "check the logs" was not previously something anyone could act on. Read-only
 * by design: nothing here writes to, rotates, or deletes a log file.
 */
class LogsController extends AbstractActionController
{
    /** Kept small because an entry can carry a whole stack trace. */
    private const PAGE_SIZE = 50;

    private $reader = null;

    public function __construct(LogFileReader $reader)
    {
        $this->reader = $reader;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'logs');

        return new ViewModel([
            'files' => $this->reader->listFiles(),
        ]);
    }

    public function viewAction()
    {
        $this->layout()->setVariable('activeTab', 'logs');

        $files = $this->reader->listFiles();
        $file = (string) $this->params()->fromQuery('file', '');

        // No file named, or one that no longer exists: show the newest, which
        // is what someone following a "view logs" link is after anyway.
        if ($this->reader->resolve($file) === null) {
            $file = $files === [] ? '' : $files[0]['name'];
        }

        $search = trim((string) $this->params()->fromQuery('q', ''));
        $level = strtoupper(trim((string) $this->params()->fromQuery('level', '')));
        $offset = max(0, (int) $this->params()->fromQuery('offset', 0));

        if (!in_array($level, ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true)) {
            $level = '';
        }

        $result = $file === ''
            ? ['entries' => [], 'hasMore' => false, 'truncated' => false]
            : $this->reader->read($file, $offset, self::PAGE_SIZE, $search, $level === '' ? null : $level);

        return new ViewModel([
            'files' => $files,
            'file' => $file,
            'search' => $search,
            'level' => $level,
            'offset' => $offset,
            'pageSize' => self::PAGE_SIZE,
            'entries' => $result['entries'],
            'hasMore' => $result['hasMore'],
            'truncated' => $result['truncated'],
        ]);
    }

    public function downloadAction()
    {
        $file = (string) $this->params()->fromQuery('file', '');
        $path = $this->reader->resolve($file);

        if ($path === null) {
            return $this->redirect()->toRoute('logs', ['action' => 'index']);
        }

        // Streamed rather than read into a string: a day's log can be larger
        // than the memory limit, and reading it in would take the site down
        // rather than serve the download.
        $response = new Stream();
        $response->setStream(fopen($path, 'rb'));
        $response->setStatusCode(200);
        $response->setStreamName(basename($path));

        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'text/plain; charset=utf-8');
        $headers->addHeaderLine('Content-Disposition', 'attachment; filename="' . basename($path) . '"');
        $headers->addHeaderLine('Content-Length', (string) filesize($path));

        return $response;
    }
}
