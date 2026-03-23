<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Contracts;

use Posterity\MediaLibrary\Core\Asset;

interface MetadataDriver
{
    public function get(StorageAdapter $storage, string $path): ?Asset;

    public function save(StorageAdapter $storage, Asset $asset): bool;

    public function delete(StorageAdapter $storage, string $path): bool;

    public function findByUuid(string $uuid, StorageAdapter $storage): ?Asset;
}
