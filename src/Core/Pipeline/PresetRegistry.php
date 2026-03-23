<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Pipeline;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\BlurEffect;
use Posterity\MediaLibrary\Core\Effects\CoverEffect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\FlipEffect;
use Posterity\MediaLibrary\Core\Effects\FlopEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Core\Effects\GreyscaleEffect;
use Posterity\MediaLibrary\Core\Effects\ResizeEffect;
use Posterity\MediaLibrary\Core\Effects\WatermarkEffect;

class PresetRegistry
{
    private array $presets = [];

    /** @var array<string, class-string<Effect>> */
    private array $effectTypes = [
        'resize'    => ResizeEffect::class,
        'cover'     => CoverEffect::class,
        'blur'      => BlurEffect::class,
        'greyscale' => GreyscaleEffect::class,
        'flip'      => FlipEffect::class,
        'flop'      => FlopEffect::class,
        'format'    => FormatEffect::class,
        'watermark' => WatermarkEffect::class,
    ];

    /**
     * Register a third-party effect class under a type name so it can be used
     * in YAML / array preset definitions and appear in the UI catalog.
     *
     * @param class-string<Effect> $class
     */
    public function registerEffectType(string $type, string $class): void
    {
        $this->effectTypes[$type] = $class;
    }

    /** @return array<string, EffectDefinition> */
    public function catalog(): array
    {
        $result = [];

        foreach ($this->effectTypes as $type => $class) {
            $result[$type] = $class::definition();
        }

        return $result;
    }

    public function register(string $name, array $effects): void
    {
        $this->presets[$name] = $effects;
    }

    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }

    public function get(string $name): array
    {
        return $this->presets[$name] ?? [];
    }

    public function loadFromYaml(string $yamlPath): void
    {
        if (! file_exists($yamlPath)) {
            throw new RuntimeException("Preset YAML not found: {$yamlPath}");
        }

        $data = Yaml::parseFile($yamlPath);

        $this->loadFromArray($data['presets'] ?? $data);
    }

    public function loadFromArray(array $config): void
    {
        foreach ($config as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $operations = $definition['operations'] ?? [];
            $effects    = [];

            foreach ($operations as $operation) {
                $effect = $this->buildEffect($operation);
                if ($effect !== null) {
                    $effects[] = $effect;
                }
            }

            $this->presets[$name] = $effects;
        }
    }

    private function buildEffect(array $operation): ?Effect
    {
        $type = array_key_first($operation);

        if ($type === null || ! isset($this->effectTypes[$type])) {
            return null;
        }

        $params = $operation[$type] ?? [];
        $class  = $this->effectTypes[$type];

        return $this->instantiateEffect($class, $type, $params);
    }

    /** @param class-string<Effect> $class */
    private function instantiateEffect(string $class, string $type, array $params): ?Effect
    {
        return match ($type) {
            'resize'    => new ResizeEffect(
                width:  (int) ($params['w'] ?? $params['width'] ?? 0),
                height: isset($params['h']) || isset($params['height'])
                            ? (int) ($params['h'] ?? $params['height'])
                            : null,
            ),
            'cover'     => new CoverEffect(
                width:  (int) ($params['w'] ?? $params['width'] ?? 0),
                height: (int) ($params['h'] ?? $params['height'] ?? 0),
                focusX: (int) ($params['focus_x'] ?? 50),
                focusY: (int) ($params['focus_y'] ?? 50),
            ),
            'blur'      => new BlurEffect((int) ($params['amount'] ?? 10)),
            'greyscale' => new GreyscaleEffect(),
            'flip'      => new FlipEffect(),
            'flop'      => new FlopEffect(),
            'format'    => new FormatEffect(
                type:    (string) ($params['type'] ?? 'jpg'),
                quality: (int) ($params['quality'] ?? 80),
            ),
            'watermark' => new WatermarkEffect(
                imageData: '',
                position:  (string) ($params['position'] ?? 'bottom-right'),
                opacity:   (int) ($params['opacity'] ?? 80),
                imagePath: (string) ($params['path'] ?? ''),
            ),
            default => $this->instantiateCustomEffect($class, $params),
        };
    }

    /** @param class-string<Effect> $class */
    private function instantiateCustomEffect(string $class, array $params): ?Effect
    {
        $definition = $class::definition();
        $args       = [];

        foreach ($definition->parameters as $parameter) {
            $args[$parameter->name] = $params[$parameter->name] ?? $parameter->default;
        }

        return new $class(...$args);
    }
}
