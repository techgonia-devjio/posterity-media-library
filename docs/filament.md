# Filament Integration

## FocusPoint Form Field

The `FocusPoint` component provides a visual, click-to-set focal point editor inside Filament resource forms.

```php
use Posterity\MediaLibrary\Filament\Forms\Components\FocusPoint;

public static function form(Form $form): Form
{
    return $form->schema([
        FocusPoint::make('focus')
            ->src(fn (Get $get) => Media::url(
                    Media::get($get('image_path'))
                )->resize(600)->toUrl()
            )
            ->label('Image Focus Point'),
    ]);
}
```

### How It Works

- Renders an image with an Alpine.js overlay.
- Clicking on the image computes the click position as an `x%,y%` string.
- The value is stored in the form field and synced with Livewire via `$wire.entangle`.
- The visual indicator (white circle) moves to the click position in real time.

### Saving the Focus Point

Extract `x` and `y` in your `save()` / `afterStateUpdated()` hook:

```php
FocusPoint::make('focus')
    ->afterStateUpdated(function (string $state, Get $get) {
        [$x, $y] = explode(',', $state);

        $asset = Media::get($get('image_path'));
        $asset->focusX = (int) $x;
        $asset->focusY = (int) $y;
        Media::save($asset);
    }),
```

## Publishing Views

```bash
php artisan vendor:publish --tag=posterity-media-config
```

The Blade view is at `resources/views/vendor/visifan/filament/forms/components/focus-point.blade.php`.

## Related Pages

- [Focus Points](focus-points.md)
- [Installation](installation.md)
