<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core;

use Posterity\MediaLibrary\Core\Contracts\CacheAdapter;
use Posterity\MediaLibrary\Core\Contracts\MetadataDriver;
use Posterity\MediaLibrary\Core\Contracts\Processor;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;
use Posterity\MediaLibrary\Support\MetadataExtractor;
use Posterity\MediaLibrary\Support\UrlGenerator;
use Posterity\MediaLibrary\Support\UrlSigner;

class MediaLibrary
{
    public function __construct(
        private StorageAdapter    $storage,
        private MetadataDriver    $metadata,
        private Processor         $processor,
        private PresetRegistry    $presets,
        private CacheAdapter      $cache,
        private MetadataExtractor $extractor,
        private string            $metaFolder = '.meta',
        private ?StorageAdapter   $cacheStorage = null,
        private ?UrlSigner        $signer = null,
        private string            $transformBase = '',
        private string            $imageBase = '',
        private ?UrlSigner        $imageSigner = null,
    ) {}

    public function store(
        string          $contents,
        string          $path,
        string          $disk,
        string          $filename,
        string          $originalFilename,
        array           $extraMetadata = [],
        ?StorageAdapter $storage = null,
    ): Asset {
        $storage ??= $this->storage;
        $dest = ltrim($path . '/' . $filename, '/');

        $storage->put($dest, $contents);

        $mime      = $storage->mimeType($dest);
        $type      = MediaType::fromMime($mime);
        $ext       = pathinfo($filename, PATHINFO_EXTENSION);
        $uuid      = $this->generateUuid();
        $technical = $this->extractor->extract($storage, $dest);

        $asset = new Asset(
            uuid:             $uuid,
            path:             $dest,
            filename:         $filename,
            originalFilename: $originalFilename,
            extension:        $ext,
            disk:             $disk,
            type:             $type,
            metadata:         array_merge($technical, $extraMetadata),
            size:             $storage->size($dest),
            mimeType:         $mime,
        );

        $this->metadata->save($storage, $asset);
        $this->cache->forever("uuid:{$uuid}", $disk . '::' . $dest);

        return $asset;
    }

    public function get(string $path, ?StorageAdapter $storage = null): ?Asset
    {
        return $this->metadata->get($storage ?? $this->storage, $path);
    }

    public function findByUuid(string $uuid, ?StorageAdapter $storage = null): ?Asset
    {
        $cached = $this->cache->get("uuid:{$uuid}");

        if ($cached !== null) {
            $path = str_contains($cached, '::') ? explode('::', $cached, 2)[1] : $cached;

            return $this->metadata->get($storage ?? $this->storage, $path);
        }

        return $this->metadata->findByUuid($uuid, $storage ?? $this->storage);
    }

    public function diskForUuid(string $uuid): ?string
    {
        $cached = $this->cache->get("uuid:{$uuid}");

        if ($cached !== null && str_contains($cached, '::')) {
            return explode('::', $cached, 2)[0];
        }

        return null;
    }

    public function save(Asset $asset, ?StorageAdapter $storage = null): void
    {
        $this->metadata->save($storage ?? $this->storage, $asset);
        $this->cache->forever("uuid:{$asset->uuid}", $asset->disk . '::' . $asset->path);
    }

    public function delete(string $path, ?StorageAdapter $storage = null): bool
    {
        $storage ??= $this->storage;
        $asset    = $this->get($path, $storage);

        if ($asset === null) {
            return false;
        }

        $dir     = dirname($path);
        $slug    = str_replace('.', '_', basename($path));
        $metaDir = ltrim($dir . '/' . $this->metaFolder . '/' . $slug, '/');

        $storage->deleteDirectory($metaDir);
        $storage->delete($path);
        $this->cache->forget("uuid:{$asset->uuid}");

        return true;
    }

    public function url(Asset $asset, ?StorageAdapter $storage = null): UrlGenerator
    {
        return new UrlGenerator(
            asset:         $asset,
            processor:     $this->processor,
            presets:       $this->presets,
            storage:       $storage ?? $this->storage,
            cacheStorage:  $this->cacheStorage ?? $this->storage,
            metaFolder:    $this->metaFolder,
            signer:        $this->signer,
            transformBase: $this->transformBase,
            imageBase:     $this->imageBase,
            imageSigner:   $this->imageSigner,
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
