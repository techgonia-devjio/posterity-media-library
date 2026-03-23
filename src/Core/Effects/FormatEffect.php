<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;
use Posterity\MediaLibrary\Core\Effects\EffectParameter;

class FormatEffect implements Effect
{
    private const SUPPORTED = ['jpg', 'jpeg', 'webp', 'avif', 'png', 'gif'];

    public function __construct(
        private string $type = 'jpg',
        private int $quality = 80,
    ) {
        $this->type = strtolower($type);

        if (! in_array($this->type, self::SUPPORTED, true)) {
            throw new InvalidArgumentException(
                "Unsupported image format \"{$type}\". Supported: " . implode(', ', self::SUPPORTED)
            );
        }
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    public function getId(): string
    {
        return 'format';
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'quality' => $this->quality];
    }

    public static function isSupported(string $type): bool
    {
        return in_array(strtolower($type), self::SUPPORTED, true);
    }

    public static function definition(): EffectDefinition
    {
        return new EffectDefinition(
            id:          'format',
            label:       'Format / Quality',
            description: 'Converts the image to a specific format and compression quality.',
            parameters:  [
                new EffectParameter(
                    name:    'type',
                    type:    'select',
                    label:   'Format',
                    default: 'webp',
                    options: ['jpg' => 'JPEG', 'webp' => 'WebP', 'avif' => 'AVIF', 'png' => 'PNG', 'gif' => 'GIF'],
                ),
                new EffectParameter(name: 'quality', type: 'integer', label: 'Quality (1–100)', default: 80, min: 1, max: 100),
            ],
        );
    }
}
