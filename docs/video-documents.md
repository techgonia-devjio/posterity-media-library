# Video & Documents

## Uploading Non-Image Files

Upload works identically for all media types:

```php
$asset = Media::upload($request->file('document'), 'docs');
// $asset->type === MediaType::Document

$asset = Media::upload($request->file('video'), 'videos');
// $asset->type === MediaType::Video
```

The `MediaType` enum is resolved automatically from the file's MIME type.

## Type Detection

| MIME prefix / value | MediaType |
|---|---|
| `image/*` | `Image` |
| `video/*` | `Video` |
| `audio/*` | `Audio` |
| `application/pdf`, `application/msword`, OOXML types, `text/plain`, … | `Document` |
| anything else | `Unknown` |

## Document Metadata

Documents receive basic metadata on upload:

```php
$asset->metadata['mime_type']; // 'application/pdf'
```

Extended document metadata (page count, author) requires external tools not bundled with this package.

## Video Metadata

If [`james-heinrich/getid3`](https://github.com/JamesHeinrich/getID3) is installed, video uploads receive:

```php
$asset->metadata['duration']; // 34.56 (seconds)
$asset->metadata['width'];    // 1920
$asset->metadata['height'];   // 1080
$asset->metadata['codec'];    // 'h264'
$asset->metadata['fps'];      // 29.97
```

Install getID3:

```bash
composer require james-heinrich/getid3
```

## Video Thumbnails

Video thumbnails are **opt-in and asynchronous** — they are never generated on the fly.

This package does not auto-dispatch thumbnail generation for you. You decide where the job should run in your upload flow.

### Enable Thumbnail Generation

```php
// config/media-library.php
'video_thumbnail' => [
    'enabled'        => true,
    'ffmpeg_path'    => env('POSTERITY_FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'offset_seconds' => 1,
],
```

### Dispatch the Job

```php
use Posterity\MediaLibrary\Jobs\GenerateVideoThumbnailJob;

$asset = Media::upload($request->file('clip'), 'videos');

if (config('media-library.video_thumbnail.enabled')) {
    GenerateVideoThumbnailJob::dispatch($asset);
}
```

The job extracts a frame at `offset_seconds`, saves it as a sidecar JPEG, and updates the asset's `thumbnail` property.

### Check for Thumbnail

```php
if ($asset->hasThumbnail()) {
    echo Storage::disk($asset->disk)->url($asset->thumbnail);
} else {
    echo '/img/video-placeholder.svg';
}
```

## Image Processing on Non-Images

`Media::url($asset)->toUrl()` on a non-image asset returns the raw storage URL directly — no processing is attempted.

## Related Pages

- [Assets](assets.md)
- [Metadata](metadata.md)
- [Uploading](uploading.md)
