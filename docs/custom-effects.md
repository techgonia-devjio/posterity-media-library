# Custom Effects

Any class implementing `Posterity\MediaLibrary\Core\Contracts\Effect` can be dropped into the pipeline and will automatically appear in the UI catalog.

## Implementing the Interface

```php
<?php

namespace App\Media\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class SepiaEffect implements Effect
{
    public function __construct(private int $intensity = 70) {}

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image
            ->greyscale()
            ->colorize(red: $this->intensity, green: (int) ($this->intensity * 0.6), blue: 0);
    }

    public function getId(): string
    {
        return 'sepia';
    }

    public function toArray(): array
    {
        return ['intensity' => $this->intensity];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'sepia',
            label:       'Sepia',
            description: 'Applies a warm sepia tone to the image.',
            parameters:  [
                new EffectParameter(
                    name:    'intensity',
                    type:    'integer',
                    label:   'Intensity',
                    default: 70,
                    min:     1,
                    max:     100,
                ),
            ],
        );
    }
}
```

## Registering the Effect Type

Call `registerEffectType()` in your `AppServiceProvider::boot()` (or a Filament plugin's `boot()`):

```php
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;

app(PresetRegistry::class)->registerEffectType('sepia', SepiaEffect::class);
```

After registration the effect:

- Appears in `catalog()` so any UI can render its form automatically
- Can be used in YAML and array preset definitions by its type name
- Can be applied via `preset()` on the `UrlGenerator`

## Using the Effect

### In a preset (recommended for CMS / end-user scenarios)

```php
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;

app(PresetRegistry::class)->register('sepia-thumb', [
    new SepiaEffect(70),
]);

Media::url($asset)->preset('sepia-thumb')->toUrl();
```

### In a YAML preset file

```yaml
presets:
  sepia-thumb:
    operations:
      - sepia:
          intensity: 70
```

### Inline (developer use)

```php
// Directly on the UrlGenerator via preset(), since pipe() is internal:
app(PresetRegistry::class)->register('_sepia_inline', [new SepiaEffect(70)]);
Media::url($asset)->preset('_sepia_inline')->toUrl();
```

## CMS / End-User Flow

The `definition()` method is what makes effects usable from a non-technical UI. The intended flow when building a Filament or Livewire effect editor:

```
1. Call PresetRegistry::catalog()                 → get all available effects + their parameters
2. Render a form per effect using $param->type    → 'integer' = slider, 'select' = dropdown, etc.
3. User fills in values and saves the preset      → store as JSON in media_presets table
4. On boot, load saved presets via loadFromArray()→ presets are live without a deploy
```

```php
// AppServiceProvider::boot()
$registry = app(PresetRegistry::class);
$presets  = DB::table('media_presets')->get();

foreach ($presets as $row) {
    $registry->loadFromArray([
        $row->name => json_decode($row->definition, true),
    ]);
}
```

The `definition` JSON column mirrors the YAML structure:

```json
{
  "operations": [
    { "sepia": { "intensity": 70 } },
    { "format": { "type": "webp", "quality": 85 } }
  ]
}
```

## EffectParameter Types

| `type`      | Renders as                          | Extra fields used  |
|-------------|-------------------------------------|--------------------|
| `integer`   | Number input / slider               | `min`, `max`       |
| `select`    | Dropdown                            | `options`          |
| `boolean`   | Toggle / checkbox                   | —                  |
| `string`    | Text input                          | —                  |

## Guidelines

- `getId()` must return a **stable, lowercase, hyphen-separated string**. Changing it invalidates all cached variants that used that effect.
- `toArray()` must include every value that affects the visual output. A missing value means two visually different images can share the same cache key.
- `apply()` should return the modified image. For marker effects (like `FormatEffect`) that carry metadata but don't transform pixels, return `$image` unchanged.
- `definition()` parameters must match the constructor argument names — `instantiateCustomEffect()` uses them to call `new YourEffect(...$args)`.

## Related Pages

- [Effects](effects.md)
- [Presets](presets.md)
- [Advanced](advanced.md)
