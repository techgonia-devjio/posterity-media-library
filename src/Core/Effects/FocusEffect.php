<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class FocusEffect implements Effect
{
    public function __construct(
        private int $x = 50,
        private int $y = 50,
    ) {}

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image;
    }

    public function getId(): string
    {
        return 'focus';
    }

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'focus',
            label:       'Focus Point',
            description: 'Sets the focal point used by the Cover effect (internal cache-key marker).',
            parameters:  [
                new EffectParameter(name: 'x', type: 'integer', label: 'Focus X (0–100)', default: 50, min: 0, max: 100),
                new EffectParameter(name: 'y', type: 'integer', label: 'Focus Y (0–100)', default: 50, min: 0, max: 100),
            ],
        );
    }
}
