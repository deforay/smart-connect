<?php

/**
 * db-tools configuration, read by vendor/bin/db-tools.
 *
 * Credentials come from the same config/autoload files the app merges, so
 * db-tools always targets the database the running app talks to. Nothing is
 * duplicated here, and no .env is involved.
 *
 * Back up:
 *   php vendor/bin/db-tools backup
 *
 * Restore:
 *   php vendor/bin/db-tools restore backup/db/NAME.sql.zst
 */

declare(strict_types=1);

$autoloadDir = __DIR__ . '/config/autoload';

// Mirror the glob and merge order in config/application.config.php, so a value
// set in local.php beats the same value in global.php. constants.global.php
// matches this glob and returns an empty array, which merges harmlessly.
$merged = [];
foreach (glob($autoloadDir . '/{{,*.}global,{,*.}local}.php', GLOB_BRACE) ?: [] as $file) {
    $config = include $file;
    if (is_array($config)) {
        $merged = array_replace_recursive($merged, $config);
    }
}

$db = $merged['db'] ?? [];

// global.php carries the connection as a PDO DSN string, so host, dbname and
// port have to be read back out of it.
$dsn = (string) ($db['dsn'] ?? '');
$host = 'localhost';
$database = '';
$port = 3306;

if (preg_match('/host=([^;]+)/', $dsn, $m)) {
    $host = trim($m[1]);
}
if (preg_match('/dbname=([^;]+)/', $dsn, $m)) {
    $database = trim($m[1]);
}
if (preg_match('/port=(\d+)/', $dsn, $m)) {
    $port = (int) $m[1];
}

// local.php carries the credentials.
$user = (string) ($db['username'] ?? $db['user'] ?? 'root');
$password = (string) ($db['password'] ?? '');

// Older dashboards configured the connection with these keys instead of a DSN.
// Accept them so db-tools works on a tree that has not been converted yet.
if ($database === '' && isset($db['data-base-name'])) {
    $database = (string) $db['data-base-name'];
}
if (isset($db['data-base-host'])) {
    $host = (string) $db['data-base-host'];
}

// Housekeeping prunes backup/ by age, so routine archives written here are
// cleaned up on the same schedule as everything else the app leaves behind.
// bin/upgrade.sh deliberately writes elsewhere, and passes --retention=0, so an
// upgrade backup is never pruned and never deletes the one before it.
$backupDir = __DIR__ . '/backup/db';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

return [
    'smart-connect' => [
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'user' => $user,
        'password' => $password,
        'output_dir' => $backupDir,
        'retention' => 7,
    ],
];
