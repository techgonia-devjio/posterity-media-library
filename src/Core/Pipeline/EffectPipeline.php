<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Pipeline;

use Intervention\Image\Interfaces\ImageInterface;
use Posterity\MediaLibrary\Core\Contracts\Effect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;

class EffectPipeline
{
    private array $effects = [];

    public function pipe(Effect $effect): self
    {
        $this->effects[] = $effect;

        return $this;
    }

    public function getEffects(): array
    {
        return $this->effects;
    }

    public function isEmpty(): bool
    {
        return empty($this->effects);
    }

    public function getFormatEffect(): ?FormatEffect
    {
        foreach ($this->effects as $effect) {
            if ($effect instanceof FormatEffect) {
                return $effect;
            }
        }

        return null;
    }

    public function getCacheKey(): string
    {
        $signature = array_map(
            fn(Effect $e) => [$e->getId(), $e->toArray()],
            $this->effects,
        );

        return md5(serialize($signature));
    }

    public function run(ImageInterface $image): ImageInterface
    {
        foreach ($this->effects as $effect) {
            $image = $effect->apply($image);
        }

        return $image;
    }
}
