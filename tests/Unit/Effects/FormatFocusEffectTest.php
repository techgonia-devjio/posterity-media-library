<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Core\Effects\BlurEffect;
use Posterity\MediaLibrary\Core\Effects\FlipEffect;
use Posterity\MediaLibrary\Core\Effects\FlopEffect;
use Posterity\MediaLibrary\Core\Effects\FocusEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Core\Effects\GreyscaleEffect;

class FormatFocusEffectTest extends TestCase
{
    // ── FormatEffect ──────────────────────────────────────────────────────────

    public function test_format_effect_get_id(): void
    {
        $this->assertSame('format', (new FormatEffect('webp', 85))->getId());
    }

    public function test_format_effect_to_array(): void
    {
        $effect = new FormatEffect('webp', 85);

        $this->assertSame(['type' => 'webp', 'quality' => 85], $effect->toArray());
    }

    public function test_format_effect_get_type(): void
    {
        $this->assertSame('avif', (new FormatEffect('avif', 70))->getType());
    }

    public function test_format_effect_get_quality(): void
    {
        $this->assertSame(95, (new FormatEffect('png', 95))->getQuality());
    }

    public function test_format_effect_default_values(): void
    {
        $effect = new FormatEffect();

        $this->assertSame('jpg', $effect->getType());
        $this->assertSame(80, $effect->getQuality());
    }

    public function test_format_effect_apply_is_noop(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->never())->method($this->anything());

        $effect = new FormatEffect('webp', 80);
        $result = $effect->apply($image);

        $this->assertSame($image, $result);
    }

    public function test_format_effect_different_types_produce_different_cache_key_data(): void
    {
        $webp = new FormatEffect('webp', 80);
        $avif = new FormatEffect('avif', 80);
        $jpeg = new FormatEffect('jpg',  80);

        $this->assertNotSame($webp->toArray(), $avif->toArray());
        $this->assertNotSame($webp->toArray(), $jpeg->toArray());
    }

    public function test_format_effect_different_quality_produces_different_cache_key_data(): void
    {
        $q80 = new FormatEffect('webp', 80);
        $q95 = new FormatEffect('webp', 95);

        $this->assertNotSame($q80->toArray(), $q95->toArray());
    }

    // ── FocusEffect ───────────────────────────────────────────────────────────

    public function test_focus_effect_get_id(): void
    {
        $this->assertSame('focus', (new FocusEffect(30, 70))->getId());
    }

    public function test_focus_effect_to_array(): void
    {
        $effect = new FocusEffect(30, 70);

        $this->assertSame(['x' => 30, 'y' => 70], $effect->toArray());
    }

    public function test_focus_effect_default_values(): void
    {
        $effect = new FocusEffect();

        $this->assertSame(['x' => 50, 'y' => 50], $effect->toArray());
    }

    public function test_focus_effect_apply_is_noop(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->never())->method($this->anything());

        $effect = new FocusEffect(30, 70);
        $result = $effect->apply($image);

        $this->assertSame($image, $result);
    }

    public function test_focus_effect_different_coords_produce_different_data(): void
    {
        $a = new FocusEffect(30, 70);
        $b = new FocusEffect(70, 30);

        $this->assertNotSame($a->toArray(), $b->toArray());
    }

    // ── BlurEffect ────────────────────────────────────────────────────────────

    public function test_blur_effect_get_id(): void
    {
        $this->assertSame('blur', (new BlurEffect(10))->getId());
    }

    public function test_blur_effect_to_array(): void
    {
        $this->assertSame(['amount' => 25], (new BlurEffect(25))->toArray());
    }

    public function test_blur_effect_default_amount_is_10(): void
    {
        $effect = new BlurEffect();

        $this->assertSame(['amount' => 10], $effect->toArray());
    }

    public function test_blur_effect_apply_calls_blur_with_amount(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('blur')
            ->with(25)
            ->willReturn($image);

        $effect = new BlurEffect(25);
        $effect->apply($image);
    }

    // ── GreyscaleEffect ───────────────────────────────────────────────────────

    public function test_greyscale_effect_get_id(): void
    {
        $this->assertSame('greyscale', (new GreyscaleEffect())->getId());
    }

    public function test_greyscale_effect_to_array_is_empty(): void
    {
        $this->assertSame([], (new GreyscaleEffect())->toArray());
    }

    public function test_greyscale_effect_apply_calls_greyscale(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('greyscale')
            ->willReturn($image);

        (new GreyscaleEffect())->apply($image);
    }

    // ── FlipEffect ────────────────────────────────────────────────────────────

    public function test_flip_effect_get_id(): void
    {
        $this->assertSame('flip', (new FlipEffect())->getId());
    }

    public function test_flip_effect_to_array_is_empty(): void
    {
        $this->assertSame([], (new FlipEffect())->toArray());
    }

    public function test_flip_effect_apply_calls_flip(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('flip')
            ->willReturn($image);

        (new FlipEffect())->apply($image);
    }

    // ── FlopEffect ────────────────────────────────────────────────────────────

    public function test_flop_effect_get_id(): void
    {
        $this->assertSame('flop', (new FlopEffect())->getId());
    }

    public function test_flop_effect_to_array_is_empty(): void
    {
        $this->assertSame([], (new FlopEffect())->toArray());
    }

    public function test_flop_effect_apply_calls_flop(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('flop')
            ->willReturn($image);

        (new FlopEffect())->apply($image);
    }
}
