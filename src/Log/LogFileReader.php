<?php

declare(strict_types=1);

namespace App\Log;

use DateTimeImmutable;

/**
 * Reads the files AppLogger writes, newest entry first.
 *
 * A day's log can run to hundreds of megabytes, so nothing here loads a file
 * into memory. Entries are assembled by reading fixed-size chunks backwards
 * from the end of the file and stopping once the page is full, which makes the
 * cost of showing the newest fifty entries the same whatever the file's size.
 *
 * An entry is a header line plus every line after it that is not itself a
 * header — that is how a stack trace stays attached to the message it belongs
 * to instead of arriving as forty separate rows.
 */
final class LogFileReader
{
    private const CHUNK_SIZE = 65536;

    /**
     * How far back a filtered read will scan before giving up. A search that
     * matches nothing would otherwise walk the whole file and time the request
     * out; the viewer says so rather than appearing to find nothing.
     */
    private const MAX_SCAN_BYTES = 40 * 1024 * 1024;

    private const HEADER_PATTERN = '/^\[(?<time>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+(?<channel>[^\s.]+)\.(?<level>[A-Z]+):\s?(?<message>.*)$/s';

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * The log files, newest first.
     *
     * @return list<array{name: string, size: int, modified: int}>
     */
    public function listFiles(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = [];

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.log') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'modified' => (int) filemtime($path),
            ];
        }

        usort($files, static fn(array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * The absolute path of a log file, or null if the name is not one this
     * reader serves. Matching against the listing rather than sanitising the
     * string is what keeps a crafted name from reaching another directory.
     */
    public function resolve(string $name): ?string
    {
        foreach ($this->listFiles() as $file) {
            if ($file['name'] === $name) {
                return $this->directory . DIRECTORY_SEPARATOR . $file['name'];
            }
        }

        return null;
    }

    /**
     * A page of entries, newest first.
     *
     * @param string      $name   File name as listFiles() reports it.
     * @param int         $offset How many matching entries to skip.
     * @param int         $limit  How many to return.
     * @param string      $search Case-insensitive substring; '' matches all.
     * @param string|null $level  Minimum severity, e.g. 'WARNING'; null for all.
     *
     * @return array{entries: list<array{time: string, channel: string, level: string, message: string, context: array<string, mixed>, raw: string}>, hasMore: bool, truncated: bool}
     */
    public function read(string $name, int $offset = 0, int $limit = 50, string $search = '', ?string $level = null): array
    {
        $path = $this->resolve($name);
        $empty = ['entries' => [], 'hasMore' => false, 'truncated' => false];

        if ($path === null) {
            return $empty;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $empty;
        }

        try {
            return $this->readBackwards($handle, (int) filesize($path), max(0, $offset), max(1, $limit), $search, $level);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    private function readBackwards($handle, int $size, int $offset, int $limit, string $search, ?string $level): array
    {
        $minLevel = $level === null ? null : self::levelWeight($level);
        $needle = $search === '' ? null : mb_strtolower($search);

        $position = $size;
        $remainder = '';

        // Lines seen after an entry's header but read before it, since reading
        // backwards reaches a stack trace before the message it belongs to.
        $pending = [];

        $skipped = 0;
        $entries = [];
        $hasMore = false;
        $scanned = 0;

        while ($position > 0) {
            $length = (int) min(self::CHUNK_SIZE, $position);
            $position -= $length;

            if (fseek($handle, $position) !== 0) {
                break;
            }

            $chunk = (string) fread($handle, $length);
            $scanned += $length;

            $lines = explode("\n", $chunk . $remainder);

            // The first piece may be half a line: its start is in the chunk we
            // have not read yet, so it is held over rather than parsed now.
            $remainder = $position > 0 ? (string) array_shift($lines) : '';

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = rtrim($lines[$i], "\r");

                if (!preg_match(self::HEADER_PATTERN, $line, $matches)) {
                    if ($line !== '' || $pending !== []) {
                        $pending[] = $line;
                    }
                    continue;
                }

                $entry = self::buildEntry($matches, $line, $pending);
                $pending = [];

                if (!self::matches($entry, $needle, $minLevel)) {
                    continue;
                }

                if ($skipped < $offset) {
                    $skipped++;
                    continue;
                }

                if (count($entries) === $limit) {
                    // One past the page is all it takes to know there is more,
                    // and it costs nothing compared with counting the file.
                    $hasMore = true;
                    return ['entries' => $entries, 'hasMore' => true, 'truncated' => false];
                }

                $entries[] = $entry;
            }

            if ($scanned >= self::MAX_SCAN_BYTES) {
                return ['entries' => $entries, 'hasMore' => false, 'truncated' => true];
            }
        }

        // Reaching here means position hit 0, so the final chunk was parsed
        // whole and nothing is held back in $remainder.
        return ['entries' => $entries, 'hasMore' => $hasMore, 'truncated' => false];
    }

    /**
     * @param array<string, string> $matches
     * @param list<string>          $pending Continuation lines, newest first.
     */
    private static function buildEntry(array $matches, string $headerLine, array $pending): array
    {
        $continuation = array_reverse($pending);

        // Trailing blank lines are formatting, not content.
        while ($continuation !== [] && trim((string) end($continuation)) === '') {
            array_pop($continuation);
        }

        $raw = $continuation === [] ? $headerLine : $headerLine . "\n" . implode("\n", $continuation);

        [$message, $context] = self::splitContext($matches['message']);

        if ($continuation !== []) {
            $message .= "\n" . implode("\n", $continuation);
        }

        return [
            'time' => self::formatTime($matches['time']),
            'channel' => $matches['channel'],
            'level' => strtoupper($matches['level']),
            'message' => $message,
            'context' => $context,
            'raw' => $raw,
        ];
    }

    /**
     * Separate the message from the context and extra arrays Monolog appends.
     *
     * The formatter writes both as JSON on the end of every line, empty or
     * not, so the last two values are always them and never part of the
     * message. That is what lets a message ending in JSON survive this intact.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function splitContext(string $message): array
    {
        $context = [];

        // Exactly twice, in the order they were written: extra, then context.
        for ($pass = 0; $pass < 2; $pass++) {
            $trimmed = rtrim($message);
            $start = self::openingBracket($trimmed);

            if ($start === null) {
                break;
            }

            $decoded = json_decode(substr($trimmed, $start), true);
            if (!is_array($decoded)) {
                break;
            }

            $context = $decoded + $context;
            $message = rtrim(substr($trimmed, 0, $start));
        }

        return [$message, $context];
    }

    /**
     * Offset of the bracket opening the JSON value that closes the string, or
     * null if the string does not end in one. Bracket characters inside quoted
     * strings are skipped, since a logged value may contain them.
     */
    private static function openingBracket(string $text): ?int
    {
        $last = strlen($text) - 1;

        if ($last < 0) {
            return null;
        }

        $close = $text[$last];
        $open = match ($close) {
            '}' => '{',
            ']' => '[',
            default => null,
        };

        if ($open === null) {
            return null;
        }

        $depth = 0;
        $inString = false;

        for ($i = $last; $i >= 0; $i--) {
            $char = $text[$i];

            if ($inString) {
                // A quote closes the string only when it is not itself escaped,
                // and the escape may in turn be escaped.
                if ($char === '"' && !self::isEscaped($text, $i)) {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === $close) {
                $depth++;
            } elseif ($char === $open) {
                if (--$depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function isEscaped(string $text, int $position): bool
    {
        $slashes = 0;

        for ($i = $position - 1; $i >= 0 && $text[$i] === '\\'; $i--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    private static function formatTime(string $time): string
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $time)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($time, 0, 19));

        return $parsed === false ? $time : $parsed->format('d-M-Y H:i:s');
    }

    private static function matches(array $entry, ?string $needle, ?int $minLevel): bool
    {
        if ($minLevel !== null && self::levelWeight($entry['level']) < $minLevel) {
            return false;
        }

        return $needle === null || str_contains(mb_strtolower($entry['raw']), $needle);
    }

    /**
     * Monolog's severities as an ordering, so a WARNING filter also shows the
     * errors above it. An unknown word sorts lowest and is therefore never
     * filtered out by accident.
     */
    public static function levelWeight(string $level): int
    {
        return match (strtoupper($level)) {
            'DEBUG' => 100,
            'INFO' => 200,
            'NOTICE' => 250,
            'WARNING' => 300,
            'ERROR' => 400,
            'CRITICAL' => 500,
            'ALERT' => 550,
            'EMERGENCY' => 600,
            default => 0,
        };
    }
}
