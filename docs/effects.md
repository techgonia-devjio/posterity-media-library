# Effects

Every image transformation is an `Effect` — a self-contained class with a single `apply()` method. Effects are composed into an `EffectPipeline` and run in order.

## The Effect Interface

```php
interface Effect
{
    public function apply(ImageInterface $image): ImageInterface;
    public function getId(): string;          // stable identifier, e.g. 'blur'
    public function toArray(): array;         // serialised params for cache key
    public static function definition(): EffectDefinition; // UI metadata
}
```

## Built-in Effects

### ResizeEffect

Scale down to fit within a bounding box. Aspect ratio is preserved; the image is never upscaled.

```php
new ResizeEffect(width: 800);
new ResizeEffect(width: 800, height: 600);
```

### CoverEffect

Scale and crop to fill the exact target dimensions, using a focal point to guide the crop origin.

```php
new CoverEffect(width: 300, height: 200, focusX: 30, focusY: 70);
```

### FocusEffect

Marker effect — stores focal coordinates in the pipeline for cache key differentiation. `apply()` is a no-op. Used internally by `UrlGenerator::focus()`.

```php
new FocusEffect(x: 30, y: 70);
```

### BlurEffect

```php
new BlurEffect(amount: 10);
```

### GreyscaleEffect

```php
new GreyscaleEffect();
```

### FlipEffect

Flip vertically (top ↔ bottom).

```php
new FlipEffect();
```

### FlopEffect

Flip horizontally (left ↔ right).

```php
new FlopEffect();
```

### FormatEffect

Marker effect — declares the output format and quality. `apply()` is a no-op; the `Processor` reads this before encoding.

```php
new FormatEffect(type: 'webp', quality: 85);
```

Supported formats: `jpg`, `jpeg`, `webp`, `avif`, `png`, `gif`.

### WatermarkEffect

```php
new WatermarkEffect(
    imageData: file_get_contents('/path/to/watermark.png'),
    position:  'bottom-right',
    opacity:   80,
);
```

## Effect Catalog

Every effect exposes a static `definition()` that describes itself — its label, description, and the parameters it accepts. You can fetch the full catalog of registered effects at any time:

```php
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;

$catalog = app(PresetRegistry::class)->catalog();
// ['blur' => EffectDefinition, 'resize' => EffectDefinition, ...]

foreach ($catalog as $id => $definition) {
    echo $definition->label;       // "Blur"
    echo $definition->description; // "Applies a Gaussian blur..."

    foreach ($definition->parameters as $param) {
        // $param->name    → 'amount'
        // $param->type    → 'integer'
        // $param->label   → 'Blur Amount'
        // $param->default → 10
        // $param->min     → 1
        // $param->max     → 100
    }
}
```

This is the hook that a CMS or Filament UI uses to render effect configuration forms dynamically — no hardcoded field lists needed.

## Effect Cache Keys

Each effect contributes to the pipeline cache key via `getId()` and `toArray()`. Two pipelines with identical effects and parameters always produce the same cache key, ensuring processed variants are shared across requests.

## Related Pages

- [Custom Effects](custom-effects.md)
- [Image Processing](image-processing.md)
- [Presets](presets.md)
