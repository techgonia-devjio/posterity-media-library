<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Posterity\MediaLibrary\Core\Contracts\CacheAdapter;

class LaravelCacheAdapter implements CacheAdapter
{
    public function __construct(private CacheRepository $cache) {}

    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->cache->put($key, $value, $ttlSeconds);
    }

    public function forever(string $key, mixed $value): void
    {
        $this->cache->forever($key, $value);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }
}
