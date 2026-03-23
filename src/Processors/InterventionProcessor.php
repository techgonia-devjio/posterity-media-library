<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Processors;

use InvalidArgumentException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Processor;
use Posterity\MediaLibrary\Core\Pipeline\EffectPipeline;

class InterventionProcessor implements Processor
{
    private ImageManager $manager;

    public function __construct(string $driver = 'gd')
    {
        $this->manager = new ImageManager(
            $driver === 'imagick' ? new ImagickDriver() : new GdDriver(),
        );
    }

    public function read(string $imageData): ImageInterface
    {
        return $this->manager->read($imageData);
    }

    public function process(ImageInterface $image, EffectPipeline $pipeline): string
    {
        $image  = $pipeline->run($image);
        $format = $pipeline->getFormatEffect();

        $type    = $format?->getType()    ?? 'jpg';
        $quality = $format?->getQuality() ?? 80;

        $encoded = match ($type) {
            'webp'        => $image->toWebp($quality),
            'avif'        => $image->toAvif($quality),
            'png'         => $image->toPng(),
            'gif'         => $image->toGif(),
            'jpg', 'jpeg' => $image->toJpeg($quality),
            default       => throw new InvalidArgumentException("Unsupported format: {$type}"),
        };

        return (string) $encoded;
    }
}
