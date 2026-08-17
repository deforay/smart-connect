<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Upload checks the v1 controllers never made.
 *
 * The ingestion services index $_FILES['vlFile'] etc. directly and assume the
 * upload succeeded, so a missing or failed upload became an undefined-index
 * fatal. They also mkdir their temp folders 0777. Handlers call
 * ensureDirectory() first so the directory already exists by the time the
 * service looks, which keeps the 0777 branch unreachable without touching the
 * service.
 */
final class UploadGuard
{
    /** Accepted upload extensions. `.json.gz` presents as `gz`. */
    public const JSON_EXTENSIONS = ['json', 'gz'];

    /**
     * @return string|null Null when the upload is usable, else the reason.
     */
    public static function validate(string $field, array $allowedExtensions = self::JSON_EXTENSIONS): ?string
    {
        $file = $_FILES[$field] ?? null;

        if (!is_array($file) || !isset($file['name']) || trim((string) $file['name']) === '') {
            return sprintf('Missing file upload "%s"', $field);
        }

        // parseMultipartFormData() synthesises entries without an 'error' key.
        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            return sprintf('Upload of "%s" failed (%s)', $field, self::describeUploadError($error));
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            return sprintf('Uploaded file "%s" is empty', $field);
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return sprintf(
                'Unsupported file type ".%s" for "%s"; expected %s',
                $extension,
                $field,
                implode(' or ', array_map(static fn(string $e): string => '.' . $e, $allowedExtensions))
            );
        }

        return null;
    }

    /**
     * A body larger than post_max_size arrives with $_POST and $_FILES both
     * empty and no upload error to read — PHP has already discarded it. v1 then
     * indexed $_FILES['vlFile'] and fatalled; saying so plainly is the whole
     * improvement here, since this is what a lab with a large backlog actually
     * hits on its first sync.
     */
    public static function postMaxSizeExceeded(): bool
    {
        if ($_POST !== [] || $_FILES !== []) {
            return false;
        }

        $limit = self::bytes((string) ini_get('post_max_size'));
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        return $limit > 0 && $length > $limit;
    }

    private static function bytes(string $iniValue): int
    {
        $iniValue = trim($iniValue);
        if ($iniValue === '') {
            return 0;
        }

        $value = (int) $iniValue;

        return match (strtolower(substr($iniValue, -1))) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /** Create a directory the services would otherwise create world-writable. */
    public static function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s"', $path));
        }
    }

    private static function describeUploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'partially uploaded',
            UPLOAD_ERR_NO_FILE => 'no file sent',
            UPLOAD_ERR_NO_TMP_DIR => 'no temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'could not write to disk',
            UPLOAD_ERR_EXTENSION => 'stopped by a PHP extension',
            default => 'error code ' . $error,
        };
    }
}
