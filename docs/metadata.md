# Metadata

## Sidecar JSON Format

Each asset has a companion JSON file stored at:

```
uploads/.meta/photo_jpg/metadata.json
```

Example:

```json
{
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "path": "uploads/550e8400-....jpg",
    "filename": "550e8400-....jpg",
    "original_filename": "holiday.jpg",
    "extension": "jpg",
    "disk": "public",
    "type": "image",
    "mime_type": "image/jpeg",
    "size": 204800,
    "focus_x": 50,
    "focus_y": 50,
    "thumbnail": null,
    "metadata": {
        "width": 1920,
        "height": 1080,
        "camera": "iPhone 15 Pro",
        "iso": 64,
        "aperture": "f/1.78",
        "created_at": "2024:07:14 18:32:10",
        "gps": { "lat": 47.3769, "lng": 8.5417 },
        "title": {
            "en": "Sunset",
            "de": "Sonnenuntergang"
        }
    }
}
```

## Automatically Extracted Metadata

### Images (JPEG / TIFF)

| Key | Description |
|---|---|
| `width` | Pixel width |
| `height` | Pixel height |
| `camera` | Camera model |
| `make` | Camera manufacturer |
| `aperture` | Aperture (e.g. `f/1.78`) |
| `iso` | ISO speed |
| `exposure` | Exposure time |
| `focal_length` | Focal length |
| `software` | Editing software |
| `created_at` | EXIF creation timestamp |
| `gps` | `{ lat, lng }` if GPS EXIF is present |

### Video (requires `james-heinrich/getid3`)

| Key | Description |
|---|---|
| `duration` | Duration in seconds |
| `width` | Frame width |
| `height` | Frame height |
| `codec` | Video codec name |
| `fps` | Frames per second |
| `bitrate` | Total bitrate |

### Audio (requires `james-heinrich/getid3`)

| Key | Description |
|---|---|
| `duration` | Duration in seconds |
| `bitrate` | Bitrate |
| `artist` | ID3 artist tag |
| `album` | ID3 album tag |
| `title` | ID3 title tag |

## Adding Custom Metadata

Pass an array to `Media::upload()` or use `withMetadata()`:

```php
$asset = Media::upload($file, 'gallery', 'public', [
    'credit' => 'Jane Doe',
    'tags'   => ['nature', 'landscape'],
]);

// Or later:
$asset = $asset->withMetadata('credit', 'Jane Doe');
Media::save($asset);
```

`withMetadata()` returns a new `Asset` instance (immutable clone).

## Multilingual Fields

Store any metadata field as a locale-keyed array:

```php
$asset->metadata['title'] = ['en' => 'Sunset', 'de' => 'Sonnenuntergang'];
Media::save($asset);

$asset->getTranslated('title', 'de'); // 'Sonnenuntergang'
$asset->getTranslated('title', 'fr'); // 'Sunset' (falls back to 'en')
```

## Related Pages

- [Assets](assets.md)
- [Custom Drivers](custom-drivers.md)
