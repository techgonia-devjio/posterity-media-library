# Installation

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13
- GD extension (or ImageMagick)

## Composer

```bash
composer require posterity/media-library
```

The service provider and `Media` facade are auto-discovered.

## Publish Configuration

```bash
php artisan vendor:publish --tag=posterity-media-config
```

This publishes:

- `config/media-library.php` for package behavior
- `config/media-presets.yaml` for named image presets

## Minimal Configuration

`config/media-library.php`:

```php
return [
    'disk'         => env('POSTERITY_MEDIA_DISK', 'public'),
    'cache_disk'   => env('POSTERITY_MEDIA_CACHE_DISK', null),
    'image_driver' => 'gd',
];
```

Meaning:

- `disk`: where original uploads are stored
- `cache_disk`: where processed image variants are stored; `null` means use the same disk
- `image_driver`: `gd` or `imagick`

Ensure your `public` disk has a `url` key in `config/filesystems.php`:

```php
'public' => [
    'driver' => 'local',
    'root'   => storage_path('app/public'),
    'url'    => env('APP_URL') . '/storage',
    'visibility' => 'public',
],
```

If you plan to use signed transform URLs, make sure `APP_KEY` is set.

## First Upload

```php
use Posterity\MediaLibrary\Laravel\Facades\Media;

$asset = Media::upload($request->file('photo'), 'gallery');

echo $asset->uuid;        // 550e8400-e29b-41d4-a716-446655440000
echo $asset->path;        // gallery/550e8400-...jpg
echo $asset->type->value; // image
```

## First Transform

```php
$url = Media::url($asset)
    ->resize(1200)
    ->format('webp', 85)
    ->toUrl();
```

That call returns a browser-ready URL. On first use, the package creates a cached transformed file under the asset's `.meta/.../cache/` directory.

## Storage Link

Run `php artisan storage:link` if you are using the local `public` disk so that files are accessible from the browser.

## Optional Features

- `queue.enabled`: precompute named image presets after upload
- `transform.enabled`: signed UUID-based transform URLs
- `image.enabled`: readable path-based image URLs
- `video_thumbnail.enabled`: optional queued video thumbnails via FFmpeg

## Related Pages

- [Uploading](uploading.md)
- [Introduction](introduction.md)
- [Storage](storage.md)
