<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class FlopEffect implements Effect
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->flop();
    }

    public function getId(): string
    {
        return 'flop';
    }

    public function toArray(): array
    {
        return [];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'flop',
            label:       'Flip Horizontal',
            description: 'Mirrors the image left to right.',
            parameters:  [],
        );
    }
}
