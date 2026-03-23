# Image Processing

`Media::url($asset)` returns a `UrlGenerator`.

That object does not process anything immediately. It collects image effects first, then produces one of several result types:

- a direct cached file URL
- a signed transform URL
- a path-based image URL
- a responsive `srcset` string built from those URLs

```php
$url = Media::url($asset)
    ->resize(800)
    ->blur(5)
    ->format('webp', 85)
    ->toUrl();

// Use directly in Blade
<img src="{{ Media::url($asset)->preset('thumb') }}">
```

## What The Result Methods Return

### `toUrl(): string`

Processes the image immediately if needed, stores the cached variant, and returns the final file URL.

Use this when you want a stable URL right now in Blade, APIs, or view models.

### `toSignedUrl(): string`

Returns a signed UUID-based transform URL such as:

```text
/media/img/{uuid}?w=800&fmt=webp&sig=...
```

Use this when `media-library.transform.enabled` is enabled and you want the HTTP endpoint to handle first-hit generation.

### `toImageUrl(): string`

Returns a readable path-based image URL such as:

```text
/img/gallery/hero.jpg?w=1600&fmt=webp
```

Use this when `media-library.image.enabled` is enabled and you prefer URLs that reflect the storage path.

### `srcset(array $widths): string`

Builds a responsive `srcset` from direct cached file URLs.

### `signedSrcset(array $widths): string`

Builds a responsive `srcset` from signed UUID-based transform URLs.

### `imageSrcset(array $widths): string`

Builds a responsive `srcset` from readable path-based image URLs.

## How Processing Works

1. Each fluent method adds an effect to the pipeline.
2. A cache key is computed from the full effect pipeline.
3. If a cached file exists at `.meta/{slug}/cache/{key}.{ext}`, its URL is returned immediately.
4. Otherwise the original image is loaded, the pipeline is applied, the result is saved to cache, and its URL is returned.

Cached variants survive across requests. Deleting the asset removes all its variants.

On non-image assets, `toUrl()` simply returns the original file URL and no processing is attempted.

## All Fluent Methods

### `preset(string $name)`

Apply a named preset (defined in `presets.yaml` or `config/media-library.php`).

```php
Media::url($asset)->preset('thumb')->toUrl();
```

### `resize(int $width, ?int $height = null)`

Scale down to fit within the given bounding box. Aspect ratio is always preserved.

```php
->resize(800)        // width only
->resize(800, 600)   // max bounding box
```

### `cover(int $width, int $height)`

Scale and crop to fill the exact target dimensions. Uses the asset's stored focus point by default.

```php
->cover(300, 200)
```

### `focus(int $x, int $y)`

Override the focal point for the next `cover()` call. `x` and `y` are percentages (0–100).

```php
->focus(30, 70)->cover(300, 200)
```

### `blur(int $amount = 10)`

```php
->blur()     // amount = 10
->blur(25)
```

### `greyscale()`

```php
->greyscale()
```

### `flip()`

Flip vertically (top ↔ bottom).

### `flop()`

Flip horizontally (left ↔ right).

### `format(string $type, int $quality = 80)`

Set the output encoding. Supported: `jpg`, `webp`, `avif`, `png`, `gif`.

```php
->format('webp', 85)
->format('avif', 70)
```

### `watermark(string $storagePath, string $position = 'bottom-right', int $opacity = 80)`

Overlay a watermark image loaded from storage.

```php
->watermark('watermarks/logo.png', 'bottom-right', 60)
```

## Example Outputs

### Direct cached URL

```php
$url = Media::url($asset)
    ->format('webp')
    ->toUrl();
```

### Signed transform URL

```php
$url = Media::url($asset)
    ->resize(1200)
    ->format('webp', 85)
    ->toSignedUrl();
```

### Path-based image URL

```php
$url = Media::url($asset)
    ->cover(1600, 900)
    ->format('webp', 85)
    ->toImageUrl();
```

### Responsive srcset

```php
$srcset = Media::url($asset)
    ->format('webp')
    ->srcset([320, 640, 1280]);

// "http://…/cache/abc123.webp 320w, http://…/cache/def456.webp 640w, …"
```

### `__toString(): string`

Alias of `toUrl()`, which is why this works in Blade:

```php
<img src="{{ Media::url($asset)->preset('thumb') }}">
```

## Related Pages

- [Effects](effects.md)
- [Presets](presets.md)
- [Focus Points](focus-points.md)
- [Storage](storage.md)
