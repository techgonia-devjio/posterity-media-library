# Advanced Usage

## Framework-Agnostic Core

The `Core/` namespace has zero Laravel dependencies. Use it in any PHP 8.3+ project:

```php
use Posterity\MediaLibrary\Core\MediaLibrary;
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;
use Posterity\MediaLibrary\Drivers\JsonSidecarDriver;
use Posterity\MediaLibrary\Drivers\NullCacheAdapter;
use Posterity\MediaLibrary\Processors\InterventionProcessor;
use Posterity\MediaLibrary\Support\MetadataExtractor;

// Bring your own StorageAdapter implementation
$storage = new MyStorageAdapter('/var/www/uploads', 'https://cdn.example.com');

$registry = new PresetRegistry();
$registry->loadFromYaml('/path/to/presets.yaml');

$library = new MediaLibrary(
    storage:   $storage,
    metadata:  new JsonSidecarDriver('.meta'),
    processor: new InterventionProcessor('gd'),
    presets:   $registry,
    cache:     new NullCacheAdapter(),
    extractor: new MetadataExtractor(),
);

$asset = $library->store(
    contents:         file_get_contents('/tmp/upload.jpg'),
    path:             'gallery',
    disk:             'local',
    filename:         'my-photo.jpg',
    originalFilename: 'holiday.jpg',
);

$url = $library->url($asset)->resize(800)->format('webp')->toUrl();
```

## Binding Custom Implementations

All core contracts are bound in the service container — override any of them in `AppServiceProvider`:

```php
use Posterity\MediaLibrary\Core\Contracts\MetadataDriver;
use Posterity\MediaLibrary\Core\Contracts\CacheAdapter;
use Posterity\MediaLibrary\Core\Contracts\Processor;

public function register(): void
{
    $this->app->bind(MetadataDriver::class, MyMetadataDriver::class);
    $this->app->bind(CacheAdapter::class, MyRedisCacheAdapter::class);
    $this->app->bind(Processor::class, MyCloudProcessor::class);
}
```

## Direct Pipeline Access

Build a pipeline manually without going through `UrlGenerator`:

```php
use Posterity\MediaLibrary\Core\Pipeline\EffectPipeline;
use Posterity\MediaLibrary\Core\Effects\ResizeEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Processors\InterventionProcessor;

$pipeline = new EffectPipeline();
$pipeline->pipe(new ResizeEffect(400));
$pipeline->pipe(new FormatEffect('webp', 80));

$processor = new InterventionProcessor('gd');
$image     = $processor->read(file_get_contents('photo.jpg'));
$encoded   = $processor->process($image, $pipeline);

file_put_contents('photo_400.webp', $encoded);
```

## StorageAdapter Contract

```php
interface StorageAdapter {
    public function exists(string $path): bool;
    public function get(string $path): ?string;
    public function put(string $path, string $contents): bool;
    public function delete(string $path): bool;
    public function deleteDirectory(string $path): bool;
    public function url(string $path): string;
    public function path(string $path): string;    // absolute local path
    public function size(string $path): int;
    public function mimeType(string $path): string;
    public function files(string $directory): array;
    public function allFiles(string $directory = ''): array;
}
```

## CacheAdapter Contract

```php
interface CacheAdapter {
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttlSeconds): void;
    public function forever(string $key, mixed $value): void;
    public function forget(string $key): void;
}
```

## Testing Without Laravel

Use `NullCacheAdapter` and a test `StorageAdapter` implementation backed by an in-memory array:

```php
class ArrayStorageAdapter implements StorageAdapter
{
    private array $files = [];

    public function put(string $path, string $contents): bool {
        $this->files[$path] = $contents;
        return true;
    }

    public function get(string $path): ?string {
        return $this->files[$path] ?? null;
    }

    public function exists(string $path): bool {
        return isset($this->files[$path]);
    }

    // … implement remaining methods
}
```

## Related Pages

- [Custom Effects](custom-effects.md)
- [Custom Drivers](custom-drivers.md)
- [Storage](storage.md)
