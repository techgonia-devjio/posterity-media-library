<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class GreyscaleEffect implements Effect
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->greyscale();
    }

    public function getId(): string
    {
        return 'greyscale';
    }

    public function toArray(): array
    {
        return [];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'greyscale',
            label:       'Greyscale',
            description: 'Converts the image to black and white.',
            parameters:  [],
        );
    }
}
