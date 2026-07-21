<?php

namespace Application\Service;

/**
 * Transparent method-call cache — the replacement for
 * Laminas\Cache\Pattern\ObjectCache. Every method call on the proxy is
 * served from cache when possible, keyed by object key + method + arguments.
 *
 * $objectKey separates caches for objects of the same class pointing at
 * different backing tables (e.g. current vs archived samples).
 */
class CachedMethodProxy
{
    private readonly string $objectKey;

    public function __construct(
        private readonly object $object,
        private readonly FileCacheUtility $cache,
        ?string $objectKey = null,
        private readonly int $ttl = 86400
    ) {
        $this->objectKey = $objectKey ?? get_class($object);
    }

    public function __call(string $method, array $args): mixed
    {
        $key = hash('sha256', serialize([$this->objectKey, $method, $args]));
        return $this->cache->get($key, fn() => $this->object->$method(...$args), [], $this->ttl);
    }

    public function __get(string $name): mixed
    {
        return $this->object->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->object->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->object->$name);
    }

    public function __unset(string $name): void
    {
        unset($this->object->$name);
    }
}
