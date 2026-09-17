<?php

namespace Application\Service;

use Exception;
use FilesystemIterator;
use RuntimeException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Ported from vlsm (App\Utilities\FileCacheUtility) so both projects share
 * the same caching surface. When $enabled is false (development), a
 * NullAdapter stands in — every get() recomputes, nothing is stored —
 * matching the old BlackHole behaviour.
 */
class FileCacheUtility
{
    private string $prefix = 'app_cache_';
    private readonly string $cacheDir;
    private readonly AdapterInterface $adapter;
    private readonly TagAwareAdapter $tagAwareAdapter;

    public function __construct(string $cacheDir, bool $enabled = true, int $defaultTtl = 86400)
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        if ($enabled) {
            // Cached query results may contain sensitive data. Web and cron share an owner.
            if (is_link($this->cacheDir)
                || (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0700, true) && !is_dir($this->cacheDir))
                || !@chmod($this->cacheDir, 0700)) {
                throw new RuntimeException('Cannot secure the application cache directory.');
            }
        }
        $this->adapter = $enabled
            ? new FilesystemAdapter('', $defaultTtl, $this->cacheDir)
            : new NullAdapter();
        $this->tagAwareAdapter = new TagAwareAdapter($this->adapter);
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    private function applyPrefix(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key, callable $computeValueCallback, ?array $tags = [], int $expiration = 3600): mixed
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->get($prefixedKey, function (ItemInterface $item) use ($computeValueCallback, $tags, $expiration) {
            $value = call_user_func($computeValueCallback, $item);

            $item->set($value);
            $item->expiresAfter($expiration);
            if ($tags !== null && $tags !== []) {
                $item->tag($tags);
            }
            return $value;
        });
    }

    public function set(string $key, $value, ?array $tags = [], int $expiration = 3600): bool
    {
        $prefixedKey = $this->applyPrefix($key);

        try {
            // Use PSR-6 getItem()/save() so this truly OVERWRITES the key. The
            // contracts get() only runs its callback on a cache MISS, so using it
            // here silently no-ops whenever the key already exists (a set() that
            // can't update is not a set()).
            $item = $this->tagAwareAdapter->getItem($prefixedKey);
            $item->set($value);
            $item->expiresAfter($expiration);
            if ($tags !== null && $tags !== []) {
                $item->tag($tags);
            }
            return $this->tagAwareAdapter->save($item);
        } catch (Exception $e) {
            error_log('Cache set failed for key ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->delete($prefixedKey);
    }

    public function clear(): bool
    {
        // Clear without following directory links before resetting Symfony's tag state.
        // The adapter's own hash-directory scan follows directory symlinks.
        if (!$this->adapter instanceof NullAdapter && !$this->clearFilesystem()) {
            return false;
        }

        try {
            return $this->tagAwareAdapter->clear();
        } catch (Exception $e) {
            error_log('Cache adapter clear failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Best-effort recursive removal of the on-disk cache. Returns true if the
     * cache directory is empty afterwards (nothing left to serve stale data),
     * false if at least one entry survived (e.g. foreign-owned files this
     * process genuinely cannot remove).
     */
    private function clearFilesystem(): bool
    {
        if (is_link($this->cacheDir)) {
            return false;
        }
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        try {
            return $this->clearDirectory($this->cacheDir);
        } catch (Exception $e) {
            error_log('Cache filesystem clear failed: ' . $e->getMessage());
            return false;
        }
    }

    private function clearDirectory(string $directory): bool
    {
        // Unlink needs write access to the parent, not to the file itself.
        // Repair access before descending so owner-unreadable shards can be cleared.
        if (!@chmod($directory, 0700)) {
            return false;
        }
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $item) {
            $path = $item->getPathname();
            // Check links first: isDir() and chmod() otherwise follow their targets.
            if ($item->isLink() || !$item->isDir()) {
                @unlink($path);
            } elseif ($this->clearDirectory($path)) {
                @rmdir($path);
            }
        }

        return !(new FilesystemIterator($directory))->valid();
    }

    public function invalidateTags(array $tags): bool
    {
        return $this->tagAwareAdapter->invalidateTags($tags);
    }

    /**
     * Check if a cache item exists and is not expired
     */
    public function hasItem(string $key): bool
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->hasItem($prefixedKey);
    }

    /**
     * Get multiple cache items at once
     */
    public function getMultiple(array $keys): iterable
    {
        $prefixedKeys = array_map([$this, 'applyPrefix'], $keys);
        return $this->tagAwareAdapter->getItems($prefixedKeys);
    }

    /**
     * Prune expired items (if supported by adapter)
     */
    public function prune(): bool
    {
        try {
            if (method_exists($this->tagAwareAdapter, 'prune')) {
                return $this->tagAwareAdapter->prune();
            }

            if (method_exists($this->adapter, 'prune')) {
                return $this->adapter->prune();
            }

            return true;
        } catch (Exception $e) {
            error_log('Cache prune failed: ' . $e->getMessage());
            return false;
        }
    }
}
