<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Core\Effects\CoverEffect;

#[AllowMockObjectsWithoutExpectations]
class CoverEffectTest extends TestCase
{
    public function test_get_id_returns_cover(): void
    {
        $effect = new CoverEffect(200, 200);

        $this->assertSame('cover', $effect->getId());
    }

    public function test_to_array_contains_all_four_keys(): void
    {
        $effect = new CoverEffect(300, 200, 30, 70);

        $this->assertSame([
            'width'   => 300,
            'height'  => 200,
            'focus_x' => 30,
            'focus_y' => 70,
        ], $effect->toArray());
    }

    public function test_default_focus_is_50_50(): void
    {
        $effect = new CoverEffect(200, 200);

        $array = $effect->toArray();

        $this->assertSame(50, $array['focus_x']);
        $this->assertSame(50, $array['focus_y']);
    }

    // ── Crop math verification ─────────────────────────────────────────────────
    //
    // Source: 1000×500 landscape, target: 200×200 square
    //   scaleW = 200/1000 = 0.2
    //   scaleH = 200/500  = 0.4   → scale = max(0.2, 0.4) = 0.4
    //   scaledW = 1000 * 0.4 = 400
    //   scaledH = 500  * 0.4 = 200

    private function makeImageMock(int $w, int $h): array
    {
        $original = $this->createMock(ImageInterface::class);
        $resized  = $this->createMock(ImageInterface::class);

        $original->method('width')->willReturn($w);
        $original->method('height')->willReturn($h);
        $original->method('resize')->willReturn($resized);
        $resized->method('crop')->willReturn($resized);

        return [$original, $resized];
    }

    public function test_apply_scales_to_cover_target_dimensions(): void
    {
        [$image, $resized] = $this->makeImageMock(1000, 500);

        // scale = 0.4 → resize to 400×200
        $image->expects($this->once())
            ->method('resize')
            ->with(400, 200)
            ->willReturn($resized);

        $resized->method('crop')->willReturn($resized);

        $effect = new CoverEffect(200, 200, 50, 50);
        $effect->apply($image);
    }

    public function test_apply_center_focus_produces_center_crop(): void
    {
        [$image, $resized] = $this->makeImageMock(1000, 500);

        // Scaled to 400×200, focus (50,50):
        //   focusPxX = 400 * 50/100 = 200
        //   focusPxY = 200 * 50/100 = 100
        //   offsetX  = max(0, min(400-200, 200-100)) = max(0, min(200, 100)) = 100
        //   offsetY  = max(0, min(200-200, 100-100)) = max(0, 0) = 0
        $resized->expects($this->once())
            ->method('crop')
            ->with(200, 200, 100, 0)
            ->willReturn($resized);

        $effect = new CoverEffect(200, 200, 50, 50);
        $effect->apply($image);
    }

    public function test_apply_top_left_focus_produces_top_left_crop(): void
    {
        [$image, $resized] = $this->makeImageMock(1000, 500);

        // focus (0,0):
        //   focusPxX = 0, focusPxY = 0
        //   offsetX  = max(0, min(200, 0 - 100)) = max(0, -100) = 0
        //   offsetY  = max(0, min(0, 0 - 100))   = max(0, -100) = 0
        $resized->expects($this->once())
            ->method('crop')
            ->with(200, 200, 0, 0)
            ->willReturn($resized);

        $effect = new CoverEffect(200, 200, 0, 0);
        $effect->apply($image);
    }

    public function test_apply_bottom_right_focus_produces_bottom_right_crop(): void
    {
        [$image, $resized] = $this->makeImageMock(1000, 500);

        // focus (100,100):
        //   focusPxX = 400 * 100/100 = 400
        //   focusPxY = 200 * 100/100 = 200
        //   offsetX  = max(0, min(200, 400 - 100)) = max(0, min(200, 300)) = 200
        //   offsetY  = max(0, min(0,   200 - 100)) = max(0, 0) = 0
        $resized->expects($this->once())
            ->method('crop')
            ->with(200, 200, 200, 0)
            ->willReturn($resized);

        $effect = new CoverEffect(200, 200, 100, 100);
        $effect->apply($image);
    }

    public function test_apply_on_portrait_image(): void
    {
        // Source: 500×1000 portrait, target: 200×200
        //   scaleW = 200/500 = 0.4
        //   scaleH = 200/1000 = 0.2  → scale = max(0.4, 0.2) = 0.4
        //   scaledW = 200, scaledH = 400
        $original = $this->createMock(ImageInterface::class);
        $resized  = $this->createMock(ImageInterface::class);

        $original->method('width')->willReturn(500);
        $original->method('height')->willReturn(1000);
        $original->expects($this->once())
            ->method('resize')
            ->with(200, 400)
            ->willReturn($resized);

        // center focus (50,50):
        //   focusPxX = 200 * 50/100 = 100
        //   focusPxY = 400 * 50/100 = 200
        //   offsetX  = max(0, min(0, 100 - 100)) = 0
        //   offsetY  = max(0, min(200, 200 - 100)) = max(0, 100) = 100
        $resized->expects($this->once())
            ->method('crop')
            ->with(200, 200, 0, 100)
            ->willReturn($resized);

        $effect = new CoverEffect(200, 200, 50, 50);
        $effect->apply($original);
    }

    public function test_crop_offset_never_goes_negative(): void
    {
        [$image, $resized] = $this->makeImageMock(1000, 500);

        // extreme focus (-10, -10) equivalent → clamped to 0
        $resized->expects($this->once())
            ->method('crop')
            ->with($this->greaterThan(0), $this->greaterThan(0), $this->greaterThanOrEqual(0), $this->greaterThanOrEqual(0))
            ->willReturn($resized);

        $effect = new CoverEffect(200, 200, 0, 0);
        $effect->apply($image);
    }

    public function test_different_focus_points_produce_different_cache_keys(): void
    {
        $centerEffect    = new CoverEffect(200, 200, 50, 50);
        $topLeftEffect   = new CoverEffect(200, 200, 0, 0);
        $bottomRight     = new CoverEffect(200, 200, 100, 100);

        $this->assertNotSame($centerEffect->toArray(), $topLeftEffect->toArray());
        $this->assertNotSame($centerEffect->toArray(), $bottomRight->toArray());
        $this->assertNotSame($topLeftEffect->toArray(), $bottomRight->toArray());
    }

    public function test_same_params_produce_identical_to_array(): void
    {
        $a = new CoverEffect(300, 200, 60, 40);
        $b = new CoverEffect(300, 200, 60, 40);

        $this->assertSame($a->toArray(), $b->toArray());
    }
}
