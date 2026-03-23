<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class FlipEffect implements Effect
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->flip();
    }

    public function getId(): string
    {
        return 'flip';
    }

    public function toArray(): array
    {
        return [];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'flip',
            label:       'Flip Vertical',
            description: 'Flips the image upside down.',
            parameters:  [],
        );
    }
}
