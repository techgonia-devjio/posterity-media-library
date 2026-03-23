# Storage

## Default Disk

Set the primary disk in `config/media-library.php`:

```php
'disk' => env('POSTERITY_MEDIA_DISK', 'public'),
```

This disk stores original uploaded files and their `.meta` sidecars.

## Cache Disk

Processed image variants can be stored on a separate disk (e.g. S3 or Cloudflare R2):

```php
'cache_disk' => env('POSTERITY_MEDIA_CACHE_DISK', null),
// null = same disk as originals
```

```
# .env
POSTERITY_MEDIA_DISK=public
POSTERITY_MEDIA_CACHE_DISK=s3
```

With this setup:
- `uploads/photo.jpg` → stored on `public`
- `uploads/.meta/photo_jpg/cache/abc123.webp` → stored on `s3`

URLs returned by `toUrl()` point to the correct disk automatically.

## Directory Layout

```
{disk root}/
└── {path}/                     ← upload directory (e.g. 'gallery')
    ├── {uuid}.jpg              ← original file
    └── .meta/
        └── {uuid}_jpg/         ← sidecar directory (dots → underscores)
            ├── metadata.json   ← serialised Asset
            └── cache/
                ├── abc123.webp ← resize(800) + format('webp')
                └── def456.jpg  ← cover(300,200)
```

## S3 / Cloudflare R2

Configure a disk in `config/filesystems.php`:

```php
's3' => [
    'driver'   => 's3',
    'key'      => env('AWS_ACCESS_KEY_ID'),
    'secret'   => env('AWS_SECRET_ACCESS_KEY'),
    'region'   => env('AWS_DEFAULT_REGION'),
    'bucket'   => env('AWS_BUCKET'),
    'url'      => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'), // for R2
    'visibility' => 'public',
],
```

Then set `POSTERITY_MEDIA_CACHE_DISK=s3` in your `.env`.

## Clearing the Image Cache

To clear all processed variants for an asset, delete it and re-upload, or delete the cache directory manually:

```bash
# From Tinker
Storage::disk('public')->deleteDirectory('gallery/.meta/myfile_jpg/cache');
```

To delete a specific variant, simply delete the corresponding file from `.meta/{slug}/cache/`.

## Related Pages

- [Installation](installation.md)
- [Uploading](uploading.md)
- [Advanced](advanced.md)
