<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class WatermarkEffect implements Effect
{
    public function __construct(
        private string $imageData = '',
        private string $position = 'bottom-right',
        private int $opacity = 80,
        private string $imagePath = '',
    ) {}

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->place($this->imageData, $this->position, 0, 0, $this->opacity);
    }

    public function isResolved(): bool
    {
        return $this->imageData !== '';
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function withImageData(string $data): self
    {
        $clone            = clone $this;
        $clone->imageData = $data;

        return $clone;
    }

    public function getId(): string
    {
        return 'watermark';
    }

    public function toArray(): array
    {
        return [
            'hash'     => md5($this->imageData),
            'position' => $this->position,
            'opacity'  => $this->opacity,
        ];
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'watermark',
            label:       'Watermark',
            description: 'Overlays a watermark image at a chosen position.',
            parameters:  [
                new EffectParameter(name: 'path',     type: 'string',  label: 'Watermark Image Path', default: ''),
                new EffectParameter(
                    name:    'position',
                    type:    'select',
                    label:   'Position',
                    default: 'bottom-right',
                    options: [
                        'top-left'     => 'Top Left',
                        'top'          => 'Top Center',
                        'top-right'    => 'Top Right',
                        'left'         => 'Center Left',
                        'center'       => 'Center',
                        'right'        => 'Center Right',
                        'bottom-left'  => 'Bottom Left',
                        'bottom'       => 'Bottom Center',
                        'bottom-right' => 'Bottom Right',
                    ],
                ),
                new EffectParameter(name: 'opacity', type: 'integer', label: 'Opacity (%)', default: 80, min: 1, max: 100),
            ],
        );
    }
}
