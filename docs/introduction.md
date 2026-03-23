# Introduction

Posterity Media Library is a Laravel-first media storage and image transformation package.

It stores original files on a filesystem disk, stores asset metadata in JSON sidecars next to those files, and generates processed image variants on demand. It supports images, video, documents, and audio through one consistent `Asset` model.

## What It Is

Posterity is a package for:

- uploading files to a disk
- storing metadata without a database table
- retrieving files by path or UUID
- generating transformed image URLs
- caching processed variants
- keeping focus-point and technical metadata with each asset

## What It Is Not

Posterity is not a full admin media manager or DAM product by itself.

It does not ship a complete browsing UI, folder manager, approval flow, or editorial dashboard. It gives you the storage model, metadata layer, and transform pipeline that those kinds of tools can build on top of.

## Storage Model

Each asset is stored as:

```text
gallery/2f2c6c8e-7b7b-4b2e-9d52-9d4f4f2f8d7a.jpg
gallery/.meta/2f2c6c8e-7b7b-4b2e-9d52-9d4f4f2f8d7a_jpg/metadata.json
gallery/.meta/2f2c6c8e-7b7b-4b2e-9d52-9d4f4f2f8d7a_jpg/cache/<hash>.webp
```

That means:

- the original file stays on your chosen disk
- metadata lives beside the file, not in a database row
- processed image variants stay grouped under the same asset

## Why Use It

- **No media tables or migrations**: metadata lives in JSON sidecars.
- **Clear asset model**: uploads return a serializable `Asset` object with UUID, path, disk, type, size, metadata, focus point, and thumbnail state.
- **On-demand image processing**: create cached variants only when a URL is requested.
- **Named presets**: define repeatable transforms in YAML or PHP config.
- **Multiple delivery styles**: direct URLs, signed transform URLs, or readable path-based image URLs.
- **Multi-disk support**: originals and processed variants can live on different disks.

## Typical Flow

```php
use Posterity\MediaLibrary\Laravel\Facades\Media;

$asset = Media::upload($request->file('photo'), 'gallery');

$thumb = Media::url($asset)->preset('thumb')->toUrl();
$hero  = Media::url($asset)->cover(1600, 900)->format('webp', 85)->toUrl();
```

After upload, you have:

- the original file on disk
- a JSON sidecar with metadata and UUID
- an `Asset` object you can store, serialize, or pass around
- lazy image URLs that create cached variants when needed

## Feature Overview

| Feature | Description |
|---|---|
| Upload API | `Media::upload($file, 'gallery')` returns an `Asset` |
| Direct transforms | `Media::url($asset)->resize(800)->format('webp')->toUrl()` |
| Named presets | `->preset('thumb')` applies YAML or PHP-defined effects |
| Focus-point cropping | `cover()` uses stored or overridden focus coordinates |
| Responsive output | `srcset()`, `signedSrcset()`, and `imageSrcset()` helpers |
| Metadata extraction | dimensions, EXIF, and optional getID3-based media metadata |
| Sidecar storage | asset metadata lives next to the original file |

## Related Pages

- [Installation](installation.md)
- [Uploading](uploading.md)
- [Assets](assets.md)
- [Image Processing](image-processing.md)
