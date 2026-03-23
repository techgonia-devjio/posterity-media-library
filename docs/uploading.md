# Uploading

`Media::upload()` stores the original file and returns an `Asset` object that represents the stored result.

## Basic Upload

```php
use Posterity\MediaLibrary\Laravel\Facades\Media;

$asset = Media::upload($request->file('photo'));
// Stored at: uploads/{uuid}.jpg (default path is 'uploads')
```

Returned data:

```php
$asset->uuid;
$asset->path;
$asset->disk;
$asset->type;
$asset->metadata;
```

## Custom Path and Disk

```php
$asset = Media::upload(
    file:     $request->file('photo'),
    path:     'gallery/2024',
    disk:     'public',
    metadata: ['title' => 'My Photo'],
);
// Stored at: gallery/2024/{uuid}.jpg
```

## Upload Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$file` | `UploadedFile` | — | The uploaded file from the request |
| `$path` | `string` | `'uploads'` | Directory within the disk |
| `$disk` | `string` | `'public'` | Storage disk name |
| `$metadata` | `array` | `[]` | Custom metadata to attach |

## Metadata on Upload

Any key-value pairs passed as `$metadata` are merged with the automatically extracted technical metadata:

```php
$asset = Media::upload($file, 'gallery', 'public', [
    'title'       => ['en' => 'Sunset', 'de' => 'Sonnenuntergang'],
    'alt'         => 'Orange sky at dusk',
    'photographer'=> 'Jane Doe',
    'tags'        => ['landscape', 'nature'],
]);
```

## File Naming

Files are renamed to `{uuid}.{extension}` on upload. The original filename is preserved in `$asset->originalFilename`.

## What Gets Written

For an image uploaded to `gallery/2024`, the package writes:

```text
gallery/2024/{uuid}.jpg
gallery/2024/.meta/{uuid_jpg}/metadata.json
```

Processed variants are not created during upload. They are created later when you request a transformed URL.

## What Happens on Upload

1. File contents are written to `{disk}/{path}/{uuid}.{extension}`.
2. MIME type and size are detected.
3. Technical metadata is extracted (EXIF for images, duration for audio/video).
4. A JSON sidecar is written to `{path}/.meta/{uuid_ext}/metadata.json`.
5. The UUID → path mapping is stored in the cache speed layer.

## Original Disk vs Cache Disk

The `$disk` argument controls where the original upload is stored.

`cache_disk` in `config/media-library.php` controls where processed image variants are stored.

That lets you do things like:

- originals on `public`, variants on `s3`
- originals on `s3`, variants on `s3`
- everything on one local disk

## Related Pages

- [Assets](assets.md)
- [Image Processing](image-processing.md)
- [Metadata](metadata.md)
- [Storage](storage.md)
