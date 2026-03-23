# Focus Points

A focus point tells the `cover()` effect where the most important part of an image is, so that the crop keeps that region visible rather than defaulting to the geometric centre.

Focus coordinates are stored as percentages (0–100) from the top-left corner:
- `(50, 50)` — centre (default)
- `(0, 0)` — top-left
- `(100, 100)` — bottom-right

## Saving a Focus Point

```php
$asset = Media::get('gallery/photo.jpg');
$asset->focusX = 30;
$asset->focusY = 70;
Media::save($asset);
```

## Using the Focus Point

`cover()` automatically reads `$asset->focusX` and `$asset->focusY`:

```php
// This crop will centre on (30%, 70%) of the image
$url = Media::url($asset)->cover(300, 200)->toUrl();
```

Override the focus point for a single URL without saving it:

```php
$url = Media::url($asset)
    ->focus(10, 10)   // top-left heavy
    ->cover(300, 200)
    ->toUrl();
```

## Filament FocusPoint Component

Install Filament and use the visual focus-point picker in your resource forms:

```php
use Posterity\MediaLibrary\Filament\Forms\Components\FocusPoint;

FocusPoint::make('focus')
    ->src(Media::url($asset)->resize(600)->toUrl())
    ->label('Focus Point'),
```

The component renders an interactive image overlay. Click anywhere on the image to set the focal point; it is stored in the form as `"x,y"` and can be split back to `focusX` / `focusY` in your save logic.

## How CoverEffect Uses the Focus Point

The `CoverEffect` performs a two-step operation:

1. **Scale** — the image is scaled so it covers the target dimensions (the larger scale factor wins).
2. **Crop** — the crop window is centred as close as possible on the focal pixel while staying within image bounds.

This means even with an extreme focus point (e.g. 5%, 5%), the resulting image will never have empty/padded areas.

## Related Pages

- [Effects](effects.md)
- [Image Processing](image-processing.md)
- [Filament](filament.md)
