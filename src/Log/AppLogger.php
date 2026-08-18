<?php

declare(strict_types=1);

namespace App\Log;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Throwable;

/**
 * Application logging, over Monolog.
 *
 * Until this existed the only record of a failure was whatever the host's PHP
 * error log happened to keep, which on a typical install is an Apache log an
 * operator cannot read without shell access. Hundreds of installs means asking
 * someone to fetch a server log is not a support path that works. These files
 * live inside the application, where the log viewer can read them.
 *
 * One rotating file per day, thirty days kept. That is the same shape the vlsm
 * stack uses, so an operator moving between the two reads the same thing.
 *
 * Every method here swallows its own failures. A logger that throws turns a
 * recoverable error into a blank page, which is worse than the missing line.
 */
final class AppLogger
{
    private const FILENAME = 'app.log';
    private const RETAIN_DAYS = 30;

    /** A single line is capped, so one runaway dump cannot fill the disk. */
    private const MAX_MESSAGE_LENGTH = 10000;

    /** Per-request ceiling, in case something logs from inside a loop. */
    private const MAX_LOGS_PER_REQUEST = 5000;

    private static ?Logger $logger = null;

    /** @var array<string, Logger> One logger, and one file, per channel. */
    private static array $channelLoggers = [];

    private static int $callCount = 0;
    private static bool $fallbackReported = false;

    public static function logDebug(string $message, array $context = []): void
    {
        self::log(Level::Debug, $message, $context);
    }

    public static function logInfo(string $message, array $context = []): void
    {
        self::log(Level::Info, $message, $context);
    }

    public static function logWarning(string $message, array $context = []): void
    {
        self::log(Level::Warning, $message, $context);
    }

    public static function logError(string $message, array $context = []): void
    {
        self::log(Level::Error, $message, $context);
    }

    /**
     * Log a throwable with the detail needed to find it in the source: class,
     * message, origin, and the trace. The trace is the reason this method
     * exists — a message alone rarely says which call site produced it.
     */
    public static function logThrowable(Throwable $e, string $note = '', array $context = []): void
    {
        $message = $note !== '' ? $note . ': ' . $e->getMessage() : $e->getMessage();

        self::log(Level::Error, $message, $context + [
            'type' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    public static function log(Level|string $level, string $message, array $context = []): void
    {
        try {
            if (++self::$callCount > self::MAX_LOGS_PER_REQUEST) {
                return;
            }

            if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
                $message = substr($message, 0, self::MAX_MESSAGE_LENGTH) . '... [truncated]';
            }

            $context += self::callerInfo();

            self::getLogger()->log($level, $message, self::sanitize($context));
        } catch (Throwable $e) {
            self::reportFallback('log() failed: ' . $e->getMessage());
        }
    }

    /**
     * Write to a channel with its own file, rather than the application log.
     *
     * Browser errors are the case this exists for. They arrive in volume, they
     * are reported by whoever happens to be using a page, and mixing them into
     * the server log would bury the server's own failures under them.
     */
    public static function logToChannel(string $channel, Level|string $level, string $message, array $context = []): void
    {
        try {
            if (++self::$callCount > self::MAX_LOGS_PER_REQUEST) {
                return;
            }

            if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
                $message = substr($message, 0, self::MAX_MESSAGE_LENGTH) . '... [truncated]';
            }

            self::channelLogger($channel)->log($level, $message, self::sanitize($context));
        } catch (Throwable $e) {
            self::reportFallback('logToChannel() failed: ' . $e->getMessage());
        }
    }

    private static function channelLogger(string $channel): Logger
    {
        // The channel names a file, so anything that is not plainly a filename
        // is replaced rather than trusted.
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '-', $channel) ?: 'channel';

        if (isset(self::$channelLoggers[$safe])) {
            return self::$channelLoggers[$safe];
        }

        $logger = new Logger($safe);
        $handler = self::rotatingHandler($safe . '.log');

        $logger->pushHandler($handler ?? new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Level::Warning));

        return self::$channelLoggers[$safe] = $logger;
    }

    public static function getLogger(): Logger
    {
        if (self::$logger instanceof Logger) {
            return self::$logger;
        }

        self::$logger = new Logger('app');
        $handler = self::rotatingHandler(self::FILENAME);

        // Something has to catch the line. Without a handler Monolog discards
        // it silently, which is the failure this class was written to end.
        self::$logger->pushHandler($handler ?? new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Level::Warning));

        return self::$logger;
    }

    /**
     * A day-rotated handler for one file, or null if the directory cannot be
     * written to. The caller decides what to fall back to.
     */
    private static function rotatingHandler(string $filename): ?RotatingFileHandler
    {
        try {
            $logDir = self::logPath();

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }

            if (!is_dir($logDir) || !is_writable($logDir)) {
                self::reportFallback('log directory is not writable: ' . $logDir);
                return null;
            }

            $handler = new RotatingFileHandler(
                $logDir . DIRECTORY_SEPARATOR . $filename,
                self::RETAIN_DAYS,
                self::level(),
                true,
                0664
            );
            $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');

            // One entry per line, with line breaks escaped inside the JSON
            // rather than written raw. A stack trace spread over forty physical
            // lines cannot be told apart from forty entries, and the viewer has
            // to be able to make that distinction.
            //
            // Context and extra are always written, even when empty, so the
            // viewer can recover them by stripping exactly two trailing JSON
            // values. A message that itself ends in JSON would otherwise be
            // misread as carrying its own context.
            //
            // Note that includeStacktraces() is deliberately not called: it
            // turns inline line breaks back on, and logThrowable() already puts
            // the full trace in the context itself.
            $handler->setFormatter(new LineFormatter(null, 'Y-m-d H:i:s', false, false));

            return $handler;
        } catch (Throwable $e) {
            self::reportFallback('handler setup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Where the log files live. LOG_PATH wins if a deployment sets it, so an
     * install can put the logs on another volume without patching code.
     */
    public static function logPath(): string
    {
        if (defined('LOG_PATH')) {
            return (string) LOG_PATH;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'logs';
    }

    private static function level(): Level
    {
        $configured = defined('LOG_LEVEL') ? (string) LOG_LEVEL : (getenv('LOG_LEVEL') ?: 'DEBUG');

        return match (strtoupper($configured)) {
            'INFO' => Level::Info,
            'WARN', 'WARNING' => Level::Warning,
            'ERROR' => Level::Error,
            default => Level::Debug,
        };
    }

    /**
     * The file and line that called the logger, which is what an operator
     * reading the viewer actually wants. Callers may override either key.
     */
    private static function callerInfo(): array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? '';
            if ($file !== '' && $file !== __FILE__) {
                return ['file' => $file, 'line' => $frame['line'] ?? 0];
            }
        }

        return [];
    }

    /**
     * Context goes into the line verbatim, so anything unbounded is cut here:
     * a request payload or a result set would otherwise become the log.
     */
    private static function sanitize(array $context, int $depth = 0): array
    {
        $clean = [];
        $items = 0;

        foreach ($context as $key => $value) {
            if (++$items > 50) {
                $clean['...'] = '[truncated]';
                break;
            }

            if (is_string($value)) {
                // A trace is the one value worth keeping at length: cutting it
                // at the same limit as everything else tends to remove exactly
                // the frames that say where the failure came from.
                $limit = $key === 'trace' ? 8000 : 1000;
                $clean[$key] = strlen($value) > $limit ? substr($value, 0, $limit) . '... [truncated]' : $value;
            } elseif (is_array($value)) {
                $clean[$key] = $depth >= 4 ? '[max depth]' : self::sanitize($value, $depth + 1);
            } elseif ($value instanceof Throwable) {
                $clean[$key] = $value;
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = '[' . get_debug_type($value) . ']';
            }
        }

        return $clean;
    }

    /**
     * Said once per request. Repeating it would fill the very log that is
     * already failing to be written.
     */
    private static function reportFallback(string $reason): void
    {
        if (self::$fallbackReported) {
            return;
        }

        self::$fallbackReported = true;
        @error_log('AppLogger: ' . str_replace(["\r", "\n"], ' ', $reason));
    }
}
