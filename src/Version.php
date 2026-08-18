<?php

declare(strict_types=1);

namespace App;

/**
 * Where the running code thinks it is: its version and the ref it was deployed
 * from.
 *
 * composer.json's top-level `version` is the single source of truth for the
 * application version. bin/migrate stamps it into dash_global_config as
 * `app_version`, the footer renders it, and bin/check-version-sync compares it
 * against the schema version the database reports.
 */
final class Version
{
    private static ?string $app = null;

    /** false once resolution has run and found nothing. */
    private static string|false|null $ref = null;

    /** Project root, the directory holding composer.json. */
    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * The application version from composer.json, or an empty string if
     * composer.json is missing or unparseable.
     */
    public static function app(): string
    {
        if (self::$app === null) {
            self::$app = self::readComposerVersion();
        }

        return self::$app;
    }

    /**
     * The ref this install was deployed from, or null if it cannot be
     * determined.
     *
     * Two sources, in order: the working tree's git metadata, then VERSION.txt,
     * which bin/upgrade.sh writes at deploy time. An rsynced instance never
     * receives .git, so on a deployed dashboard the file is the only signal.
     *
     * The value is a short commit sha for a git deploy. Installs upgraded from
     * a tarball have no commit, so bin/upgrade.sh stamps a `tarball-<date>`
     * marker instead and this returns that marker unchanged.
     */
    public static function ref(): ?string
    {
        if (self::$ref === null) {
            self::$ref = self::resolveRef();
        }

        return self::$ref === false ? null : self::$ref;
    }

    private static function readComposerVersion(): string
    {
        $composer = self::root() . '/composer.json';
        if (!is_readable($composer)) {
            return '';
        }

        $data = json_decode((string) file_get_contents($composer), true);

        return is_array($data) ? (string) ($data['version'] ?? '') : '';
    }

    private static function resolveRef(): string|false
    {
        $sha = self::shaFromGitDir(self::root() . '/.git');
        if ($sha !== false) {
            return substr($sha, 0, 7);
        }

        return self::refFromVersionFile();
    }

    private static function shaFromGitDir(string $gitDir): string|false
    {
        if (!is_dir($gitDir)) {
            return false;
        }

        $head = @file_get_contents($gitDir . '/HEAD');
        if ($head === false) {
            return false;
        }

        $head = trim($head);
        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return $head; // detached HEAD
        }

        if (!str_starts_with($head, 'ref:')) {
            return false;
        }

        return self::shaFromRef($gitDir, trim(substr($head, 4)));
    }

    private static function shaFromRef(string $gitDir, string $ref): string|false
    {
        $loose = $gitDir . '/' . $ref;
        if (is_readable($loose)) {
            $sha = trim((string) @file_get_contents($loose));
            if (preg_match('/^[0-9a-f]{40}$/', $sha)) {
                return $sha;
            }
        }

        // `git gc` packs refs away, leaving no loose file to read.
        $packed = $gitDir . '/packed-refs';
        if (!is_readable($packed)) {
            return false;
        }

        $handle = @fopen($packed, 'r');
        if ($handle === false) {
            return false;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if ($line === '' || $line[0] === '#' || $line[0] === '^') {
                    continue;
                }
                $parts = preg_split('/\s+/', trim($line)) ?: [];
                if (count($parts) === 2 && $parts[1] === $ref && preg_match('/^[0-9a-f]{40}$/', $parts[0])) {
                    return $parts[0];
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    private static function refFromVersionFile(): string|false
    {
        $versionFile = self::root() . '/VERSION.txt';
        if (!is_readable($versionFile)) {
            return false;
        }

        $handle = @fopen($versionFile, 'r');
        if ($handle === false) {
            return false;
        }

        $line = trim((string) fgets($handle, 64));
        fclose($handle);

        if (preg_match('/^[0-9a-f]{7,40}$/', $line)) {
            return substr($line, 0, 7);
        }

        // bin/upgrade.sh's tarball marker, kept whole so the deploy date shows.
        if (preg_match('/^tarball-[0-9A-Za-z._-]{1,32}$/', $line)) {
            return $line;
        }

        return false;
    }
}
