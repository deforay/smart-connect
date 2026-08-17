<?php

/**
 * Idempotent setup for the API v2 enrollment key (`api.enrollment_key` in
 * config/autoload/custom.global.php).
 *
 * Usage:
 *   php bin/generate-enrollment-key.php            # idempotent: no-op if a strong key is already
 *                                                  #             set, otherwise generate and write one
 *   php bin/generate-enrollment-key.php --show     # print the configured key (never touch the file)
 *   php bin/generate-enrollment-key.php --print    # print a fresh key to stdout (never touch the file)
 *   php bin/generate-enrollment-key.php --force    # rotate even an existing strong key
 *
 * The key is a 64-character lowercase hex string (32 random bytes). A LIS
 * presents it once to POST /api/v2/enroll and receives its own Bearer token in
 * exchange, so this one secret is what lets hundreds of installations enroll
 * themselves without anyone issuing credentials by hand.
 *
 * Why idempotent by default: the key is copied into the per-country vlsm
 * installer config. Rotating it does not break clients that already hold a
 * token, but it does stop any installation that has not enrolled yet from ever
 * enrolling, until its config is updated. Running this on a configured install
 * has to be a safe no-op so it can sit in composer post-update.
 *
 * Use --show to read the key back when setting up the vlsm side.
 */

declare(strict_types=1);

const MINIMUM_KEY_LENGTH = 32;

$argv = $_SERVER['argv'] ?? [];
$opts = [
    'print' => in_array('--print', $argv, true),
    'show' => in_array('--show', $argv, true),
    'force' => in_array('--force', $argv, true),
    'help' => in_array('--help', $argv, true) || in_array('-h', $argv, true),
];

if ($opts['help']) {
    fwrite(STDOUT, file_get_contents(__FILE__, false, null, 0, 1500) . "\n");
    exit(0);
}

if ($opts['print']) {
    fwrite(STDOUT, bin2hex(random_bytes(32)) . "\n");
    exit(0);
}

$configPath = dirname(__DIR__) . '/config/autoload/custom.global.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "ERROR: custom.global.php not found at $configPath\n");
    fwrite(STDERR, "Copy config/autoload/custom.global.dist.php to custom.global.php first.\n");
    exit(2);
}

/**
 * Detect the existing key by including the config, not by matching text: the
 * file is a PHP array, so the parser is the only thing that reads it the way
 * the application does.
 */
function read_configured_key(string $configPath): ?string
{
    try {
        $config = include $configPath;
    } catch (Throwable) {
        // Unparseable. Leave it to the writer, which only ever makes a targeted edit.
        return null;
    }

    if (!is_array($config)) {
        return null;
    }

    $key = $config['api']['enrollment_key'] ?? null;

    return is_string($key) ? trim($key) : null;
}

$existing = read_configured_key($configPath);
$isStrong = $existing !== null && strlen($existing) >= MINIMUM_KEY_LENGTH;

if ($opts['show']) {
    if (!$isStrong) {
        fwrite(STDERR, "No enrollment key is configured. Run this script without --show to generate one.\n");
        exit(1);
    }
    fwrite(STDOUT, $existing . "\n");
    exit(0);
}

if ($isStrong && !$opts['force']) {
    fwrite(STDOUT, "api.enrollment_key is already set (length=" . strlen((string) $existing) . "). No change. Pass --force to rotate.\n");
    exit(0);
}

if (!is_writable($configPath)) {
    fwrite(STDERR, "ERROR: $configPath is not writable\n");
    exit(2);
}

$content = file_get_contents($configPath);
if ($content === false) {
    fwrite(STDERR, "ERROR: read failed for $configPath\n");
    exit(2);
}

$key = bin2hex(random_bytes(32));
$keyLine = "        'enrollment_key' => '" . $key . "',";

/**
 * Every write is a targeted edit so comments and neighbouring settings survive.
 * preg_replace_callback rather than preg_replace, so a '$' or '\' in the
 * generated key can never be read as a backreference.
 */
$keyLinePattern = "/^[ \t]*'enrollment_key'[ \t]*=>.*$/m";
$apiBlockPattern = "/^[ \t]*'api'[ \t]*=>[ \t]*\[[^\r\n]*\R/m";
$returnPattern = "/^return[ \t]*\[[^\r\n]*\R/m";

if (preg_match($keyLinePattern, $content)) {
    $content = preg_replace_callback($keyLinePattern, static fn(): string => $keyLine, $content, 1);
    $action = $opts['force'] ? 'Rotated api.enrollment_key' : 'Filled empty api.enrollment_key';
} elseif (preg_match($apiBlockPattern, $content)) {
    $content = preg_replace_callback(
        $apiBlockPattern,
        static fn(array $m): string => $m[0] . $keyLine . "\n",
        $content,
        1
    );
    $action = "Inserted api.enrollment_key into the existing 'api' block";
} elseif (preg_match($returnPattern, $content)) {
    $block = "    'api' => [\n" . $keyLine . "\n        'legacy_sunset' => null,\n        'debug' => false,\n    ],\n";
    $content = preg_replace_callback(
        $returnPattern,
        static fn(array $m): string => $m[0] . $block,
        $content,
        1
    );
    $action = "Added an 'api' block with a generated enrollment_key";
} else {
    fwrite(STDERR, "ERROR: could not find a 'return [' or 'api' => [ anchor in $configPath\n");
    fwrite(STDERR, "Add this by hand under the top-level array:\n\n    'api' => [\n$keyLine\n    ],\n");
    exit(2);
}

// Refuse to write a file the PHP parser rejects. A broken custom.global.php
// takes down the whole application, not only the API.
$temporaryPath = $configPath . '.tmp-' . bin2hex(random_bytes(4));
if (file_put_contents($temporaryPath, $content) === false) {
    fwrite(STDERR, "ERROR: write failed for $temporaryPath\n");
    exit(2);
}

exec(sprintf('%s -l %s', escapeshellarg(PHP_BINARY), escapeshellarg($temporaryPath)), $lintOutput, $lintStatus);
if ($lintStatus !== 0) {
    unlink($temporaryPath);
    fwrite(STDERR, "ERROR: the edit would produce invalid PHP, so $configPath was left untouched.\n");
    fwrite(STDERR, implode("\n", $lintOutput) . "\n");
    exit(2);
}

if (!rename($temporaryPath, $configPath)) {
    unlink($temporaryPath);
    fwrite(STDERR, "ERROR: could not replace $configPath\n");
    exit(2);
}

fwrite(STDOUT, "$action in $configPath\n");
fwrite(STDOUT, "\n  $key\n\n");
fwrite(STDOUT, "Copy this into the vlsm installer config for this country. Read it back later with:\n");
fwrite(STDOUT, "  php bin/generate-enrollment-key.php --show\n");
exit(0);
