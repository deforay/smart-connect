<?php

namespace Application\Controller;

use App\Log\AppLogger;
use Application\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Monolog\Level;

/**
 * Receives error reports from the browser.
 *
 * A JavaScript error leaves no trace on the server: the screen misbehaves for
 * whoever is using it, and nobody else ever hears about it. These reports are
 * that missing half, written to their own log file so they do not bury the
 * server's failures.
 *
 * Reachable without a privilege, and without a session, because errors happen
 * on the login page too and an error report that requires a working page is
 * worth little. What that costs is a public endpoint that writes to a log, so
 * every field is capped and the reporter in the layout dedupes before sending.
 */
class ClientErrorController extends AbstractActionController
{
    private const CHANNEL = 'client';

    private const MAX_MESSAGE = 2000;
    private const MAX_STACK = 8000;
    private const MAX_URL = 1000;
    private const MAX_USER_AGENT = 500;

    public function indexAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return $this->accepted();
        }

        $report = $this->parseBody($request);
        $message = $this->clip(trim((string) ($report['message'] ?? '')), self::MAX_MESSAGE);

        if ($message === '') {
            return $this->accepted();
        }

        $session = new Container('credo');

        $context = array_filter([
            'page' => $this->clip((string) ($report['url'] ?? ''), self::MAX_URL),
            'source' => $this->clip((string) ($report['source'] ?? ''), self::MAX_URL),
            'line' => isset($report['line']) ? (int) $report['line'] : null,
            'column' => isset($report['column']) ? (int) $report['column'] : null,
            'stack' => $this->clip((string) ($report['stack'] ?? ''), self::MAX_STACK),
            'user_agent' => $this->clip($this->header($request, 'User-Agent'), self::MAX_USER_AGENT),
            'user_id' => $session->userId,
            'username' => $session->username,
        ], static fn($value): bool => $value !== null && $value !== '' && $value !== 0);

        AppLogger::logToChannel(self::CHANNEL, $this->level($report['level'] ?? ''), $message, $context);

        return $this->accepted();
    }

    /**
     * Beacons are sent as a JSON blob, so the body has to be decoded here
     * rather than read from the post parameters. A form-encoded body is still
     * accepted, for a caller that cannot send a beacon.
     */
    private function parseBody($request): array
    {
        $content = trim((string) $request->getContent());

        if ($content !== '' && ($content[0] === '{' || $content[0] === '[')) {
            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $request->getPost()->toArray();
    }

    /**
     * getHeader() hands back a header object, not its value, and an absent
     * header comes back as false.
     */
    private function header($request, string $name): string
    {
        $header = $request->getHeader($name);

        return $header === false ? '' : (string) $header->getFieldValue();
    }

    private function level(string $reported): Level
    {
        return match (strtolower($reported)) {
            'warning' => Level::Warning,
            'info' => Level::Info,
            default => Level::Error,
        };
    }

    private function clip(string $value, int $max): string
    {
        return strlen($value) <= $max ? $value : substr($value, 0, $max) . '...';
    }

    /**
     * Always 202, whatever the report contained. The browser cannot act on a
     * rejection, and a failing error reporter that then reports its own failure
     * is a loop worth designing out.
     */
    private function accepted()
    {
        $this->getResponse()->setStatusCode(202);

        $model = new JsonModel(['received' => true]);
        $model->setTerminal(true);

        return $model;
    }
}
