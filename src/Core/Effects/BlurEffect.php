<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class BlurEffect implements Effect
{
    public function __construct(private int $amount = 10) {}

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->blur($this->amount);
    }

    public function getId(): string
    {
        return 'blur';
    }

    public function toArray(): array
    {
        return ['amount' => $this->amount];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'blur',
            label:       'Blur',
            description: 'Applies a Gaussian blur to soften the image.',
            parameters:  [
                new EffectParameter(name: 'amount', type: 'integer', label: 'Blur Amount', default: 10, min: 1, max: 100),
            ],
        );
    }
}
