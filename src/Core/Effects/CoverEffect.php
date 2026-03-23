<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class CoverEffect implements Effect
{
    public function __construct(
        private int $width,
        private int $height,
        private int $focusX = 50,
        private int $focusY = 50,
    ) {}

    public function apply(ImageInterface $image): ImageInterface
    {
        $srcW = $image->width();
        $srcH = $image->height();

        $scaleW = $this->width / $srcW;
        $scaleH = $this->height / $srcH;
        $scale  = max($scaleW, $scaleH);

        $scaledW = (int) round($srcW * $scale);
        $scaledH = (int) round($srcH * $scale);

        $image = $image->resize($scaledW, $scaledH);

        $focusPxX = (int) round($scaledW * $this->focusX / 100);
        $focusPxY = (int) round($scaledH * $this->focusY / 100);

        $offsetX = (int) max(0, min($scaledW - $this->width,  $focusPxX - $this->width  / 2));
        $offsetY = (int) max(0, min($scaledH - $this->height, $focusPxY - $this->height / 2));

        return $image->crop($this->width, $this->height, $offsetX, $offsetY);
    }

    public function getId(): string
    {
        return 'cover';
    }

    public function toArray(): array
    {
        return [
            'width'   => $this->width,
            'height'  => $this->height,
            'focus_x' => $this->focusX,
            'focus_y' => $this->focusY,
        ];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'cover',
            label:       'Cover / Crop',
            description: 'Crops the image to exact dimensions, keeping the focus point visible.',
            parameters:  [
                new EffectParameter(name: 'width',   type: 'integer', label: 'Width (px)',   default: 800, min: 1, max: 8000),
                new EffectParameter(name: 'height',  type: 'integer', label: 'Height (px)',  default: 600, min: 1, max: 8000),
                new EffectParameter(name: 'focus_x', type: 'integer', label: 'Focus X (0–100)', default: 50, min: 0, max: 100),
                new EffectParameter(name: 'focus_y', type: 'integer', label: 'Focus Y (0–100)', default: 50, min: 0, max: 100),
            ],
        );
    }
}
