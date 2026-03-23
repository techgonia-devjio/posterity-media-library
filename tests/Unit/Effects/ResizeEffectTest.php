<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Core\Effects\ResizeEffect;

class ResizeEffectTest extends TestCase
{
    public function test_get_id_returns_resize(): void
    {
        $effect = new ResizeEffect(800);

        $this->assertSame('resize', $effect->getId());
    }

    public function test_to_array_with_width_only(): void
    {
        $effect = new ResizeEffect(800);

        $this->assertSame(['width' => 800, 'height' => null], $effect->toArray());
    }

    public function test_to_array_with_width_and_height(): void
    {
        $effect = new ResizeEffect(800, 600);

        $this->assertSame(['width' => 800, 'height' => 600], $effect->toArray());
    }

    public function test_apply_calls_scale_with_width_only(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('scale')
            ->with(800, null)
            ->willReturn($image);

        $effect = new ResizeEffect(800);
        $result = $effect->apply($image);

        $this->assertSame($image, $result);
    }

    public function test_apply_calls_scale_with_width_and_height(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('scale')
            ->with(800, 600)
            ->willReturn($image);

        $effect = new ResizeEffect(800, 600);
        $effect->apply($image);
    }

    public function test_different_dimensions_produce_different_cache_keys(): void
    {
        $small = new ResizeEffect(200);
        $large = new ResizeEffect(1920);

        $this->assertNotSame($small->toArray(), $large->toArray());
    }

    public function test_cache_key_differentiates_width_only_vs_width_height(): void
    {
        $widthOnly  = new ResizeEffect(800);
        $withHeight = new ResizeEffect(800, 600);

        $this->assertNotSame($widthOnly->toArray(), $withHeight->toArray());
    }
}
