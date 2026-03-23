<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Contracts;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Pipeline\EffectPipeline;

interface Processor
{
    public function read(string $imageData): ImageInterface;

    public function process(ImageInterface $image, EffectPipeline $pipeline): string;
}
