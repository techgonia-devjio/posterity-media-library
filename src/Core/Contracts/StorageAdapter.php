<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Contracts;

interface StorageAdapter
{
    public function exists(string $path): bool;

    public function get(string $path): ?string;

    public function put(string $path, string $contents): bool;

    public function delete(string $path): bool;

    public function deleteDirectory(string $path): bool;

    public function url(string $path): string;

    public function path(string $path): string;

    public function size(string $path): int;

    public function mimeType(string $path): string;

    public function files(string $directory): array;

    public function allFiles(string $directory = ''): array;
}
