<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Drivers;

use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\Contracts\MetadataDriver;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;

class JsonSidecarDriver implements MetadataDriver
{
    public function __construct(private string $metaFolder = '.meta') {}

    public function get(StorageAdapter $storage, string $path): ?Asset
    {
        $sidecar = $this->sidecarPath($path);

        if (! $storage->exists($sidecar)) {
            return null;
        }

        $json = $storage->get($sidecar);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? Asset::fromArray($data) : null;
    }

    public function save(StorageAdapter $storage, Asset $asset): bool
    {
        $sidecar = $this->sidecarPath($asset->path);

        return $storage->put(
            $sidecar,
            json_encode($asset->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    public function delete(StorageAdapter $storage, string $path): bool
    {
        return $storage->deleteDirectory($this->sidecarDirectory($path));
    }

    public function findByUuid(string $uuid, StorageAdapter $storage): ?Asset
    {
        foreach ($storage->allFiles() as $file) {
            if (basename($file) !== 'metadata.json') {
                continue;
            }

            $json = $storage->get($file);

            if ($json === null) {
                continue;
            }

            $data = json_decode($json, true);

            if (is_array($data) && ($data['uuid'] ?? null) === $uuid) {
                return Asset::fromArray($data);
            }
        }

        return null;
    }

    private function sidecarDirectory(string $path): string
    {
        $dir  = dirname($path);
        $slug = str_replace('.', '_', basename($path));
        $base = $dir === '.' ? '' : $dir . '/';

        return $base . $this->metaFolder . '/' . $slug;
    }

    private function sidecarPath(string $path): string
    {
        return $this->sidecarDirectory($path) . '/metadata.json';
    }
}
