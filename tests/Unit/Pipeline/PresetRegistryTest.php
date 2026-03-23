<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Posterity\MediaLibrary\Core\Effects\BlurEffect;
use Posterity\MediaLibrary\Core\Effects\CoverEffect;
use Posterity\MediaLibrary\Core\Effects\FlipEffect;
use Posterity\MediaLibrary\Core\Effects\FlopEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Core\Effects\GreyscaleEffect;
use Posterity\MediaLibrary\Core\Effects\ResizeEffect;
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;

class PresetRegistryTest extends TestCase
{
    // ── register / has / get ──────────────────────────────────────────────────

    public function test_has_returns_false_for_unregistered_preset(): void
    {
        $registry = new PresetRegistry();

        $this->assertFalse($registry->has('thumb'));
    }

    public function test_register_and_has(): void
    {
        $registry = new PresetRegistry();
        $registry->register('thumb', [new ResizeEffect(150)]);

        $this->assertTrue($registry->has('thumb'));
    }

    public function test_get_returns_registered_effects(): void
    {
        $effect   = new ResizeEffect(150);
        $registry = new PresetRegistry();
        $registry->register('thumb', [$effect]);

        $effects = $registry->get('thumb');

        $this->assertCount(1, $effects);
        $this->assertSame($effect, $effects[0]);
    }

    public function test_get_returns_empty_array_for_unknown_preset(): void
    {
        $registry = new PresetRegistry();

        $this->assertSame([], $registry->get('nonexistent'));
    }

    public function test_register_overwrites_existing_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->register('thumb', [new ResizeEffect(100)]);
        $registry->register('thumb', [new ResizeEffect(200), new BlurEffect(5)]);

        $effects = $registry->get('thumb');

        $this->assertCount(2, $effects);
    }

    // ── loadFromArray ─────────────────────────────────────────────────────────

    public function test_load_from_array_registers_resize_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'small' => [
                'operations' => [
                    ['resize' => ['w' => 300, 'h' => 200]],
                ],
            ],
        ]);

        $effects = $registry->get('small');

        $this->assertCount(1, $effects);
        $this->assertInstanceOf(ResizeEffect::class, $effects[0]);
        $this->assertSame(['width' => 300, 'height' => 200], $effects[0]->toArray());
    }

    public function test_load_from_array_registers_resize_width_only(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'hero' => [
                'operations' => [
                    ['resize' => ['w' => 1920]],
                ],
            ],
        ]);

        $effects = $registry->get('hero');

        $this->assertCount(1, $effects);
        $this->assertSame(['width' => 1920, 'height' => null], $effects[0]->toArray());
    }

    public function test_load_from_array_registers_cover_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'avatar' => [
                'operations' => [
                    ['cover' => ['w' => 80, 'h' => 80]],
                ],
            ],
        ]);

        $effects = $registry->get('avatar');

        $this->assertCount(1, $effects);
        $this->assertInstanceOf(CoverEffect::class, $effects[0]);
        $this->assertSame(['width' => 80, 'height' => 80, 'focus_x' => 50, 'focus_y' => 50], $effects[0]->toArray());
    }

    public function test_load_from_array_cover_with_custom_focus(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'offset' => [
                'operations' => [
                    ['cover' => ['w' => 100, 'h' => 100, 'focus_x' => 20, 'focus_y' => 80]],
                ],
            ],
        ]);

        $effects = $registry->get('offset');

        $this->assertSame(['width' => 100, 'height' => 100, 'focus_x' => 20, 'focus_y' => 80], $effects[0]->toArray());
    }

    public function test_load_from_array_registers_blur_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'soft' => [
                'operations' => [
                    ['blur' => ['amount' => 15]],
                ],
            ],
        ]);

        $effects = $registry->get('soft');

        $this->assertCount(1, $effects);
        $this->assertInstanceOf(BlurEffect::class, $effects[0]);
        $this->assertSame(['amount' => 15], $effects[0]->toArray());
    }

    public function test_load_from_array_registers_greyscale_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'bw' => [
                'operations' => [
                    ['greyscale' => []],
                ],
            ],
        ]);

        $effects = $registry->get('bw');

        $this->assertInstanceOf(GreyscaleEffect::class, $effects[0]);
    }

    public function test_load_from_array_registers_flip_and_flop(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'mirror' => [
                'operations' => [
                    ['flip' => []],
                    ['flop' => []],
                ],
            ],
        ]);

        $effects = $registry->get('mirror');

        $this->assertCount(2, $effects);
        $this->assertInstanceOf(FlipEffect::class, $effects[0]);
        $this->assertInstanceOf(FlopEffect::class, $effects[1]);
    }

    public function test_load_from_array_registers_format_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'webp80' => [
                'operations' => [
                    ['format' => ['type' => 'webp', 'quality' => 80]],
                ],
            ],
        ]);

        $effects = $registry->get('webp80');

        $this->assertCount(1, $effects);
        $this->assertInstanceOf(FormatEffect::class, $effects[0]);
        $this->assertSame(['type' => 'webp', 'quality' => 80], $effects[0]->toArray());
    }

    public function test_load_from_array_ignores_unknown_operations(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'test' => [
                'operations' => [
                    ['totally_unknown_op' => ['x' => 1]],
                    ['resize' => ['w' => 100]],
                ],
            ],
        ]);

        $effects = $registry->get('test');

        // Only the resize effect should be added (unknown op → null → skipped)
        $this->assertCount(1, $effects);
        $this->assertInstanceOf(ResizeEffect::class, $effects[0]);
    }

    public function test_load_from_array_skips_non_array_definitions(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'valid'   => ['operations' => [['resize' => ['w' => 100]]]],
            'invalid' => 'not-an-array',
        ]);

        $this->assertTrue($registry->has('valid'));
        $this->assertFalse($registry->has('invalid'));
    }

    public function test_multiple_load_from_array_calls_accumulate_presets(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray(['thumb' => ['operations' => [['resize' => ['w' => 150]]]]]);
        $registry->loadFromArray(['hero'  => ['operations' => [['resize' => ['w' => 1920]]]]]);

        $this->assertTrue($registry->has('thumb'));
        $this->assertTrue($registry->has('hero'));
    }

    public function test_load_from_array_with_multi_effect_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->loadFromArray([
            'thumb' => [
                'operations' => [
                    ['cover'  => ['w' => 150, 'h' => 150]],
                    ['format' => ['type' => 'webp', 'quality' => 80]],
                ],
            ],
        ]);

        $effects = $registry->get('thumb');

        $this->assertCount(2, $effects);
        $this->assertInstanceOf(CoverEffect::class, $effects[0]);
        $this->assertInstanceOf(FormatEffect::class, $effects[1]);
    }

    // ── loadFromYaml ──────────────────────────────────────────────────────────

    public function test_load_from_yaml_throws_for_missing_file(): void
    {
        $registry = new PresetRegistry();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $registry->loadFromYaml('/non/existent/presets.yaml');
    }

    public function test_load_from_yaml_loads_presets(): void
    {
        $yaml = <<<YAML
presets:
  mini:
    operations:
      - resize: { w: 64 }
      - format: { type: webp, quality: 70 }
YAML;

        $tmp = tempnam(sys_get_temp_dir(), 'presets_') . '.yaml';
        file_put_contents($tmp, $yaml);

        try {
            $registry = new PresetRegistry();
            $registry->loadFromYaml($tmp);

            $this->assertTrue($registry->has('mini'));
            $effects = $registry->get('mini');
            $this->assertCount(2, $effects);
            $this->assertInstanceOf(ResizeEffect::class, $effects[0]);
            $this->assertInstanceOf(FormatEffect::class, $effects[1]);
        } finally {
            unlink($tmp);
        }
    }

    public function test_load_from_yaml_with_package_presets_file(): void
    {
        $yamlPath = __DIR__ . '/../../../config/presets.yaml';

        if (! file_exists($yamlPath)) {
            $this->markTestSkipped('Package presets.yaml not found.');
        }

        $registry = new PresetRegistry();
        $registry->loadFromYaml($yamlPath);

        $this->assertTrue($registry->has('thumb'));
        $this->assertTrue($registry->has('hero'));
        $this->assertTrue($registry->has('og'));
    }
}
