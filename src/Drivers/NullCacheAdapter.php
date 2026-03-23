<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Drivers;

use Posterity\MediaLibrary\Core\Contracts\CacheAdapter;

class NullCacheAdapter implements CacheAdapter
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void {}

    public function forever(string $key, mixed $value): void {}

    public function forget(string $key): void {}
}
