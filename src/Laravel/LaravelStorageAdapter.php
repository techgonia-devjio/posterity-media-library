<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel;

use Illuminate\Contracts\Filesystem\Filesystem;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;

class LaravelStorageAdapter implements StorageAdapter
{
    public function __construct(private Filesystem $disk) {}

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function get(string $path): ?string
    {
        return $this->disk->exists($path) ? $this->disk->get($path) : null;
    }

    public function put(string $path, string $contents): bool
    {
        return $this->disk->put($path, $contents);
    }

    public function delete(string $path): bool
    {
        return $this->disk->delete($path);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->disk->deleteDirectory($path);
    }

    public function url(string $path): string
    {
        return $this->disk->url($path);
    }

    public function path(string $path): string
    {
        return $this->disk->path($path);
    }

    public function size(string $path): int
    {
        return $this->disk->size($path);
    }

    public function mimeType(string $path): string
    {
        return $this->disk->mimeType($path) ?: 'application/octet-stream';
    }

    public function files(string $directory): array
    {
        return $this->disk->files($directory);
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->disk->allFiles($directory);
    }
}
