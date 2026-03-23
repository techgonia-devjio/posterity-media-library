# Custom Drivers

## Replacing the Metadata Driver

By default, metadata is stored as JSON sidecars (`JsonSidecarDriver`). Implement `MetadataDriver` to use a database, Redis, or any other backend.

```php
<?php

namespace App\Media;

use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\Contracts\MetadataDriver;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;

class DatabaseMetadataDriver implements MetadataDriver
{
    public function get(StorageAdapter $storage, string $path): ?Asset
    {
        $row = \DB::table('media_assets')->where('path', $path)->first();
        return $row ? Asset::fromArray((array) $row) : null;
    }

    public function save(StorageAdapter $storage, Asset $asset): bool
    {
        \DB::table('media_assets')->upsert(
            $asset->toArray(),
            ['path'],
        );
        return true;
    }

    public function delete(StorageAdapter $storage, string $path): bool
    {
        return (bool) \DB::table('media_assets')->where('path', $path)->delete();
    }

    public function findByUuid(string $uuid, StorageAdapter $storage): ?Asset
    {
        $row = \DB::table('media_assets')->where('uuid', $uuid)->first();
        return $row ? Asset::fromArray((array) $row) : null;
    }
}
```

Register it in a service provider:

```php
use Posterity\MediaLibrary\Core\Contracts\MetadataDriver;
use App\Media\DatabaseMetadataDriver;

$this->app->bind(MetadataDriver::class, DatabaseMetadataDriver::class);
```

## Replacing the Cache Adapter

Swap the UUID → path cache with any implementation of `CacheAdapter`:

```php
use Posterity\MediaLibrary\Core\Contracts\CacheAdapter;
use App\Media\RedisCacheAdapter;

$this->app->bind(CacheAdapter::class, RedisCacheAdapter::class);
```

The `NullCacheAdapter` is provided for testing or for apps that do not need a speed layer.

## Replacing the Image Processor

Implement `Processor` to use a different image library or a remote processing service:

```php
use Posterity\MediaLibrary\Core\Contracts\Processor;
use App\Media\CloudinaryProcessor;

$this->app->bind(Processor::class, CloudinaryProcessor::class);
```

`Processor` has three methods: `read()`, `process()`, and `supports()`. Consult the [source](../src/Core/Contracts/Processor.php) for signatures.

## Writing a StorageAdapter

Wrap any filesystem in a `StorageAdapter` to use it in non-Laravel environments or with custom drivers:

```php
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;

class LocalStorageAdapter implements StorageAdapter
{
    public function __construct(private string $root, private string $baseUrl) {}

    public function exists(string $path): bool
    {
        return file_exists($this->root . '/' . $path);
    }

    // … implement remaining methods
}
```

## Related Pages

- [Advanced](advanced.md)
- [Custom Effects](custom-effects.md)
