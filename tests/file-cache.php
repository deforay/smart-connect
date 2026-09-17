<?php

declare(strict_types=1);

// Run with: php tests/file-cache.php. Only temporary fixtures are accessed.
require dirname(__DIR__) . '/vendor/autoload.php';

use Application\Service\FileCacheUtility;

function checkCache(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeFixture(string $path): void
{
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }
    chmod($path, 0700);
    foreach (new FilesystemIterator($path) as $item) {
        removeFixture($item->getPathname());
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/file-cache-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
try {
    $directory = $root . '/cache';
    mkdir($directory, 0775);
    chmod($directory, 0775);
    $cache = new FileCacheUtility($directory);
    clearstatcache();
    checkCache((fileperms($directory) & 0777) === 0700, 'Cache root must be owner-only');
    checkCache($cache->set('key', 'old', ['tag']), 'Cache write failed');
    checkCache($cache->get('key', fn () => 'miss') === 'old', 'Cache read failed');
    checkCache($cache->clear(), 'Normal clear failed');
    checkCache($cache->get('key', fn () => 'fresh') === 'fresh', 'Clear must invalidate entries');
    checkCache($cache->set('tagged', 'value', ['tag']), 'Cache must remain writable after clear');
    $cache->invalidateTags(['tag']);
    checkCache($cache->get('tagged', fn () => 'recomputed') === 'recomputed', 'Tag invalidation failed');

    $outside = $root . '/outside';
    mkdir($outside, 0700);
    file_put_contents($outside . '/keep', 'untouched');
    chmod($outside . '/keep', 0600);
    // Use Symfony's hash-directory shape to exercise its former symlink traversal path.
    mkdir($directory . '/@/A', 0700, true);
    symlink($outside, $directory . '/@/A/B');
    symlink($outside . '/keep', $directory . '/file-link');
    symlink($outside . '/missing', $directory . '/broken-link');
    mkdir($directory . '/locked/child', 0700, true);
    file_put_contents($directory . '/locked/child/read-only', 'fixture');
    chmod($directory . '/locked/child/read-only', 0400);
    chmod($directory . '/locked', 0000);
    checkCache($cache->clear(), 'Clear must handle locked shards and symlinks');
    clearstatcache();
    checkCache(file_get_contents($outside . '/keep') === 'untouched', 'External file was modified');
    checkCache((fileperms($outside . '/keep') & 0777) === 0600, 'External permissions changed');
    checkCache((fileperms($outside) & 0777) === 0700, 'External directory permissions changed');
    checkCache(!(new FilesystemIterator($directory))->valid(), 'Cache should be empty');
    checkCache((fileperms($directory) & 0777) === 0700, 'Clear must keep the cache private');

    $disabled = new FileCacheUtility($outside, false);
    checkCache($disabled->clear(), 'Disabled cache clear failed');
    checkCache(is_file($outside . '/keep'), 'Disabled cache must not delete files');

    symlink($outside, $root . '/linked-root');
    $rejected = false;
    try {
        new FileCacheUtility($root . '/linked-root');
    } catch (RuntimeException) {
        $rejected = true;
    }
    checkCache($rejected, 'Symlink cache root must be rejected');
    echo "File cache regression checks passed.\n";
} finally {
    removeFixture($root);
}
