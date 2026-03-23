<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit\Pipeline;

use Intervention\Image\Interfaces\ImageInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\BlurEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Core\Effects\GreyscaleEffect;
use Posterity\MediaLibrary\Core\Effects\ResizeEffect;
use Posterity\MediaLibrary\Core\Pipeline\EffectPipeline;

#[AllowMockObjectsWithoutExpectations]
class EffectPipelineTest extends TestCase
{
    // ── isEmpty ───────────────────────────────────────────────────────────────

    public function test_new_pipeline_is_empty(): void
    {
        $pipeline = new EffectPipeline();

        $this->assertTrue($pipeline->isEmpty());
    }

    public function test_pipeline_is_not_empty_after_pipe(): void
    {
        $pipeline = (new EffectPipeline())->pipe(new BlurEffect());

        $this->assertFalse($pipeline->isEmpty());
    }

    // ── pipe / getEffects ─────────────────────────────────────────────────────

    public function test_pipe_returns_self(): void
    {
        $pipeline = new EffectPipeline();
        $returned = $pipeline->pipe(new BlurEffect());

        $this->assertSame($pipeline, $returned);
    }

    public function test_get_effects_returns_effects_in_insertion_order(): void
    {
        $blur  = new BlurEffect(10);
        $grey  = new GreyscaleEffect();
        $fmt   = new FormatEffect('webp', 80);

        $pipeline = (new EffectPipeline())
            ->pipe($blur)
            ->pipe($grey)
            ->pipe($fmt);

        $effects = $pipeline->getEffects();

        $this->assertCount(3, $effects);
        $this->assertSame($blur, $effects[0]);
        $this->assertSame($grey, $effects[1]);
        $this->assertSame($fmt,  $effects[2]);
    }

    public function test_can_pipe_same_effect_type_multiple_times(): void
    {
        $pipeline = (new EffectPipeline())
            ->pipe(new BlurEffect(5))
            ->pipe(new BlurEffect(10));

        $this->assertCount(2, $pipeline->getEffects());
    }

    // ── getCacheKey ───────────────────────────────────────────────────────────

    public function test_cache_key_is_a_hex_string(): void
    {
        $pipeline = (new EffectPipeline())->pipe(new ResizeEffect(800));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $pipeline->getCacheKey());
    }

    public function test_cache_key_is_deterministic(): void
    {
        $a = (new EffectPipeline())->pipe(new ResizeEffect(800))->pipe(new BlurEffect(5));
        $b = (new EffectPipeline())->pipe(new ResizeEffect(800))->pipe(new BlurEffect(5));

        $this->assertSame($a->getCacheKey(), $b->getCacheKey());
    }

    public function test_cache_key_changes_when_param_changes(): void
    {
        $a = (new EffectPipeline())->pipe(new ResizeEffect(800));
        $b = (new EffectPipeline())->pipe(new ResizeEffect(400));

        $this->assertNotSame($a->getCacheKey(), $b->getCacheKey());
    }

    public function test_cache_key_changes_with_effect_order(): void
    {
        $a = (new EffectPipeline())->pipe(new BlurEffect(10))->pipe(new ResizeEffect(800));
        $b = (new EffectPipeline())->pipe(new ResizeEffect(800))->pipe(new BlurEffect(10));

        $this->assertNotSame($a->getCacheKey(), $b->getCacheKey());
    }

    public function test_cache_key_changes_with_different_effect_types(): void
    {
        $resize    = (new EffectPipeline())->pipe(new ResizeEffect(800));
        $greyscale = (new EffectPipeline())->pipe(new GreyscaleEffect());

        $this->assertNotSame($resize->getCacheKey(), $greyscale->getCacheKey());
    }

    public function test_empty_pipeline_produces_stable_cache_key(): void
    {
        $a = new EffectPipeline();
        $b = new EffectPipeline();

        $this->assertSame($a->getCacheKey(), $b->getCacheKey());
    }

    public function test_adding_format_effect_changes_cache_key(): void
    {
        $without = (new EffectPipeline())->pipe(new ResizeEffect(800));
        $with    = (new EffectPipeline())->pipe(new ResizeEffect(800))->pipe(new FormatEffect('webp', 80));

        $this->assertNotSame($without->getCacheKey(), $with->getCacheKey());
    }

    // ── getFormatEffect ───────────────────────────────────────────────────────

    public function test_get_format_effect_returns_null_when_none_piped(): void
    {
        $pipeline = (new EffectPipeline())->pipe(new ResizeEffect(800));

        $this->assertNull($pipeline->getFormatEffect());
    }

    public function test_get_format_effect_returns_format_effect_when_piped(): void
    {
        $fmt      = new FormatEffect('webp', 80);
        $pipeline = (new EffectPipeline())->pipe(new ResizeEffect(800))->pipe($fmt);

        $this->assertSame($fmt, $pipeline->getFormatEffect());
    }

    public function test_get_format_effect_returns_first_format_effect(): void
    {
        $first  = new FormatEffect('webp', 80);
        $second = new FormatEffect('avif', 70);

        $pipeline = (new EffectPipeline())->pipe($first)->pipe($second);

        $this->assertSame($first, $pipeline->getFormatEffect());
    }

    public function test_get_format_effect_on_empty_pipeline_returns_null(): void
    {
        $this->assertNull((new EffectPipeline())->getFormatEffect());
    }

    // ── run ───────────────────────────────────────────────────────────────────

    public function test_run_returns_image_unchanged_on_empty_pipeline(): void
    {
        $image    = $this->createStub(ImageInterface::class);
        $pipeline = new EffectPipeline();

        $result = $pipeline->run($image);

        $this->assertSame($image, $result);
    }

    public function test_run_applies_effects_in_order(): void
    {
        $image = $this->createStub(ImageInterface::class);

        $order  = [];
        $effect1 = $this->makeOrderTracking($image, $order, 'first');
        $effect2 = $this->makeOrderTracking($image, $order, 'second');
        $effect3 = $this->makeOrderTracking($image, $order, 'third');

        $pipeline = (new EffectPipeline())
            ->pipe($effect1)
            ->pipe($effect2)
            ->pipe($effect3);

        $pipeline->run($image);

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function test_run_passes_result_of_each_effect_to_next(): void
    {
        $image1 = $this->createStub(ImageInterface::class);
        $image2 = $this->createStub(ImageInterface::class);
        $image3 = $this->createStub(ImageInterface::class);

        $effect1 = $this->createMock(Effect::class);
        $effect2 = $this->createMock(Effect::class);

        $effect1->method('apply')->with($image1)->willReturn($image2);
        $effect2->method('apply')->with($image2)->willReturn($image3);

        $pipeline = (new EffectPipeline())->pipe($effect1)->pipe($effect2);
        $result   = $pipeline->run($image1);

        $this->assertSame($image3, $result);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeOrderTracking(ImageInterface $image, array &$order, string $name): Effect
    {
        $effect = $this->createMock(Effect::class);
        $effect->method('apply')->willReturnCallback(function () use ($image, &$order, $name) {
            $order[] = $name;
            return $image;
        });

        return $effect;
    }
}
