# Presets

Presets are named sequences of effects. Apply them with a single method call instead of chaining every effect individually.

```php
Media::url($asset)->preset('thumb')->toUrl();
```

## YAML Presets

After publishing the config, edit `config/media-presets.yaml`:

```yaml
presets:

  thumb:
    operations:
      - cover:  { w: 150, h: 150 }
      - format: { type: webp, quality: 80 }

  hero:
    operations:
      - resize: { w: 1920 }
      - format: { type: webp, quality: 90 }

  og:
    operations:
      - cover:  { w: 1200, h: 630 }
      - format: { type: jpg, quality: 85 }
```

### Supported Operations

| Key | Parameters |
|---|---|
| `resize` | `w`, `h` (optional) |
| `cover` | `w`, `h`, `focus_x` (opt.), `focus_y` (opt.) |
| `blur` | `amount` (default 10) |
| `greyscale` | — |
| `flip` | — |
| `flop` | — |
| `format` | `type` (`jpg`/`webp`/`avif`/`png`/`gif`), `quality` (0–100) |
| `watermark` | `path`, `position`, `opacity` |

Example watermark preset:

```yaml
presets:
  branded:
    operations:
      - resize:    { w: 1600 }
      - watermark: { path: watermarks/logo.png, position: bottom-right, opacity: 70 }
      - format:    { type: webp, quality: 85 }
```

## PHP Presets (config)

Register presets programmatically in `config/media-library.php`:

```php
'presets' => [
    'avatar' => [
        'operations' => [
            ['cover'  => ['w' => 80, 'h' => 80]],
            ['format' => ['type' => 'webp', 'quality' => 75]],
        ],
    ],
],
```

PHP config presets are loaded **after** the YAML file, so they can override YAML presets.

## Registering Presets at Runtime

Inject `PresetRegistry` and call `register()` with an array of `Effect` objects:

```php
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;
use Posterity\MediaLibrary\Core\Effects\CoverEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;

app(PresetRegistry::class)->register('banner', [
    new CoverEffect(1200, 400),
    new FormatEffect('webp', 80),
]);
```

## Combining Presets with Extra Effects

```php
$url = Media::url($asset)
    ->preset('hero')
    ->blur(3)   // additional effect after the preset
    ->toUrl();
```

## Related Pages

- [Effects](effects.md)
- [Image Processing](image-processing.md)
- [Custom Effects](custom-effects.md)
