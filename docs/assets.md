# Assets

An `Asset` is the package's canonical representation of a stored file.

It is returned by `upload()`, `get()`, and `findByUuid()`, and it carries both storage information and extracted metadata.

```php
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\MediaType;

$asset = Media::get('gallery/photo.jpg');

$asset->uuid;             // '550e8400-e29b-41d4-a716-446655440000'
$asset->path;             // 'gallery/550e8400-....jpg'
$asset->filename;         // '550e8400-....jpg'
$asset->originalFilename; // 'holiday-photo.jpg'
$asset->extension;        // 'jpg'
$asset->disk;             // 'public'
$asset->type;             // MediaType::Image
$asset->mimeType;         // 'image/jpeg'
$asset->size;             // 204800  (bytes)
$asset->focusX;           // 50  (percentage, 0–100)
$asset->focusY;           // 50
$asset->metadata;         // ['width' => 1920, 'height' => 1080, ...]
```

## Identity vs Mutable Fields

These fields describe the stored file and normally do not change:

- `uuid`
- `path`
- `filename`
- `originalFilename`
- `extension`
- `disk`
- `type`

These fields are expected to change over time and can be persisted with `Media::save($asset)`:

- `metadata`
- `focusX`
- `focusY`
- `thumbnail`
- `size`
- `mimeType`

## Media Types

```php
$asset->isImage();    // true for image/* MIME types
$asset->isVideo();    // true for video/* MIME types
$asset->isDocument(); // true for PDF, DOCX, XLSX, …
$asset->isAudio();    // true for audio/* MIME types
```

The `MediaType` enum:

```php
MediaType::Image
MediaType::Video
MediaType::Document
MediaType::Audio
MediaType::Unknown
```

## Multilingual Metadata

Metadata values can be plain strings or locale-keyed arrays:

```php
// Store during upload
$asset = Media::upload($file, 'gallery', 'public', [
    'title' => ['en' => 'Sunset', 'de' => 'Sonnenuntergang'],
    'alt'   => ['en' => 'A beautiful sunset over the mountains'],
]);

// Retrieve
$asset->getTranslated('title', 'de'); // 'Sonnenuntergang'
$asset->getTranslated('title', 'fr'); // 'Sunset'  (falls back to 'en')
```

## Updating An Asset

Update the mutable properties you care about, then persist:

```php
$asset->focusX = 30;
$asset->focusY = 70;
Media::save($asset);
```

```php
$asset->metadata['alt'] = 'Homepage hero image';
$asset->thumbnail = 'videos/.meta/example_mp4/thumbnail.jpg';

Media::save($asset);
```

## Serialisation

```php
$array = $asset->toArray();   // for JSON responses, caching, etc.
$asset = Asset::fromArray($array);
```

## Related Pages

- [Uploading](uploading.md)
- [Retrieving & Deleting](retrieving.md)
- [Metadata](metadata.md)
- [Focus Points](focus-points.md)
