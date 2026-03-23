<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Contracts;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Effects\EffectDefinition;

interface Effect
{
    public function apply(ImageInterface $image): ImageInterface;

    public function getId(): string;

    public function toArray(): array;

    public static function definition(): EffectDefinition;
}
