<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Contracts;

interface CacheAdapter
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds): void;

    public function forever(string $key, mixed $value): void;

    public function forget(string $key): void;
}
