<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Media Disk
    |--------------------------------------------------------------------------
    |
    | The default storage disk for original uploaded files. Must match a disk
    | defined in config/filesystems.php.
    |
    */
    'disk' => env('POSTERITY_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Cache Disk
    |--------------------------------------------------------------------------
    |
    | Disk used to store processed image variants (thumbnails, resized copies).
    | Set to null to use the same disk as the originals (self-contained pattern).
    | Common choice for production: a separate 's3' or 'r2' bucket.
    |
    */
    'cache_disk' => env('POSTERITY_MEDIA_CACHE_DISK', null),

    /*
    |--------------------------------------------------------------------------
    | Metadata Folder
    |--------------------------------------------------------------------------
    |
    | Name of the hidden sidecar directory stored alongside each file.
    | For uploads/hero.jpg this becomes uploads/.meta/hero_jpg/.
    |
    */
    'meta_folder' => '.meta',

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    |
    | Which Intervention Image driver to use for processing.
    | Supported: "gd" (default, no extra system deps), "imagick"
    |
    */
    'image_driver' => env('POSTERITY_IMAGE_DRIVER', 'imagick'),

    /*
    |--------------------------------------------------------------------------
    | Cache Speed Layer
    |--------------------------------------------------------------------------
    |
    | Caches UUID → file path mappings so findByUuid() avoids a full disk scan.
    | Uses the application's default cache store when enabled.
    |
    */
    'cache' => [
        'enabled' => env('POSTERITY_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | When enabled, post-upload preset generation is dispatched to the queue.
    | Set precompute_presets to the preset names you want generated on upload.
    |
    */
    'queue' => [
        'enabled'             => env('POSTERITY_QUEUE_ENABLED', true),
        'name'                => env('POSTERITY_QUEUE_NAME', 'default'),
        'precompute_presets'  => ['thumb'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Thumbnails
    |--------------------------------------------------------------------------
    |
    | Optional ffmpeg-based thumbnail extraction for video assets.
    | You must dispatch GenerateVideoThumbnailJob manually after upload:
    |   GenerateVideoThumbnailJob::dispatch($asset);
    | The job silently exits when enabled is false or ffmpeg is not found.
    |
    */
    'video_thumbnail' => [
        'enabled'        => env('POSTERITY_VIDEO_THUMB_ENABLED', false),
        'ffmpeg_path'    => env('POSTERITY_FFMPEG_PATH', '/usr/bin/ffmpeg'),
        'offset_seconds' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Programmatic Preset Registration
    |--------------------------------------------------------------------------
    |
    | Define presets here in addition to (or instead of) the presets.yaml file.
    | Format mirrors the YAML structure. Keys are preset names.
    |
    | 'presets' => [
    |     'avatar' => [
    |         'operations' => [
    |             ['cover'  => ['w' => 80, 'h' => 80]],
    |             ['format' => ['type' => 'webp', 'quality' => 75]],
    |         ],
    |     ],
    | ],
    |
    */
    'presets' => [],

    /*
    |--------------------------------------------------------------------------
    | HTTP Transform Endpoint  (Glide-style)
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers a GET route at {route_prefix}/{uuid}
    | that processes images on-demand via HMAC-signed URLs.
    |
    | Flow for public disks (S3 / R2 / DigitalOcean / public local):
    |   First request → process → upload to cache → 301 redirect to CDN URL.
    |   All subsequent requests are served by the CDN — PHP is never hit again.
    |
    | Flow for private disks:
    |   Every request is streamed through PHP with long-lived Cache-Control
    |   headers so the browser / reverse proxy caches it locally.
    |
    | Usage (server-side rendering):
    |   Media::url($asset)->resize(800)->format('webp')->toUrl()
    |
    | Usage (headless / API / CDN):
    |   Media::url($asset)->resize(800)->format('webp')->toSignedUrl()
    |   // → https://app.test/media/img/{uuid}?w=800&fmt=webp&sig=abc123
    |
    | URL parameters supported by the endpoint:
    |   preset, w, h, fit (resize|cover), fx, fy, fmt, q, blur, grey, flip, flop
    |
    */
    /*
    |--------------------------------------------------------------------------
    | UUID-based HTTP Transform  (toSignedUrl)
    |--------------------------------------------------------------------------
    |
    | Registers GET /{route_prefix}/{uuid}?w=800&fmt=webp&sig=…
    | Best for server-side rendering where you know the Asset object.
    |
    */
    'transform' => [
        'enabled'      => env('POSTERITY_TRANSFORM_ENABLED', false),
        'route_prefix' => env('POSTERITY_TRANSFORM_PREFIX', 'media/img'),
        'middleware'   => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Path-based Image Engine  (toImageUrl)  ← headless / CDN
    |--------------------------------------------------------------------------
    |
    | Registers GET /{prefix}/{path} where {path} is the storage path.
    | Designed for headless CMS, API resources, and CDN-first setups where
    | the URL should be human-readable and contain the actual filename.
    |
    | Examples:
    |   /img/uploads/gallery/photo.jpg?w=800&fmt=webp
    |   /img/uploads/portrait.jpg?effect=thumb
    |   /img/uploads/hero.jpg?w=1920&h=1080&effect=cover&fmt=webp&q=90
    |
    | Supported params:
    |   effect  preset name (thumb, hero…) OR cover | resize | blur | grey | flip | flop
    |   w       width in pixels
    |   h       height in pixels
    |   fit     cover | resize  (when effect is not set)
    |   fmt     jpg | webp | avif | png
    |   q       quality 0–100  (default 80)
    |   blur    blur amount     (composable with w/h)
    |   grey    1 = greyscale
    |   flip    1 = flip vertically
    |   flop    1 = flip horizontally
    |   fx      focus-x override 0–100
    |   fy      focus-y override 0–100
    |   sig     HMAC signature (required when image.sign = true)
    |
    | Signing always uses APP_KEY — no separate env var needed.
    |
    | Response strategy:
    |   Public CDN disk (S3/R2/DO/public local) → 301 redirect to CDN URL.
    |   Private / local disk                    → stream with long cache headers.
    |
    */
    'image' => [
        'enabled'    => env('POSTERITY_IMAGE_ENGINE_ENABLED', false),
        'prefix'     => env('POSTERITY_IMAGE_PREFIX', 'img'),

        // Require HMAC signature on every request.
        // Default is false — throttle middleware is sufficient protection for most setups.
        // Enable (POSTERITY_IMAGE_SIGN=true) for private or abuse-sensitive deployments.
        'sign'       => env('POSTERITY_IMAGE_SIGN', false),

        // Rate limiting. 300 requests per minute per IP by default.
        'middleware' => ['throttle:300,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Settings
    |--------------------------------------------------------------------------
    |
    | cache_max_age — seconds for Cache-Control: max-age on processed images.
    |   Default is 31536000 (1 year), appropriate for content-addressed cache paths.
    |   Lower this if you need to be able to invalidate cached transforms faster.
    |
    */
    'delivery' => [
        'cache_max_age' => env('POSTERITY_CACHE_MAX_AGE', 31536000),
    ],

];
