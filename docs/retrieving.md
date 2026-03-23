# Retrieving & Deleting

Use these methods after upload when you need to load an existing asset, update it, or remove it together with its cached variants.

## Get by Path

```php
$asset = Media::get('gallery/550e8400-....jpg');

if ($asset === null) {
    abort(404);
}
```

## Find by UUID

UUID lookups use the cache speed layer first. If the UUID is not cached, the package falls back to scanning sidecar metadata files on the target storage.

```php
$asset = Media::findByUuid('550e8400-e29b-41d4-a716-446655440000');
```

## Save Changes

Persist modifications to focus point, metadata, or any other mutable property:

```php
$asset->focusX = 30;
$asset->focusY = 70;

Media::save($asset);
```

`save()` rewrites the JSON sidecar and refreshes the UUID → path cache entry.

## Delete

Deletes the original file **and** the entire `.meta/{slug}/` directory (including all cached variants):

```php
Media::delete('gallery/550e8400-....jpg');
```

Returns `true` on success, `false` if the asset was not found.

## Typical Lifecycle

```php
$asset = Media::upload($request->file('photo'), 'gallery');

$sameAsset = Media::get($asset->path);
$byUuid    = Media::findByUuid($asset->uuid);

$sameAsset->focusX = 35;
$sameAsset->focusY = 60;
Media::save($sameAsset);

Media::delete($sameAsset->path);
```

## Related Pages

- [Assets](assets.md)
- [Image Processing](image-processing.md)
- [Storage](storage.md)
