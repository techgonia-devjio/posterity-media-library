<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class ResizeEffect implements Effect
{
    public function __construct(
        private int $width,
        private ?int $height = null,
    ) {}

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->scale($this->width, $this->height);
    }

    public function getId(): string
    {
        return 'resize';
    }

    public function toArray(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'resize',
            label:       'Resize',
            description: 'Scales the image to fit within the given dimensions, preserving aspect ratio.',
            parameters:  [
                new EffectParameter(name: 'width',  type: 'integer', label: 'Width (px)',  default: 800, min: 1, max: 8000),
                new EffectParameter(name: 'height', type: 'integer', label: 'Height (px)', default: null, min: 1, max: 8000),
            ],
        );
    }
}
