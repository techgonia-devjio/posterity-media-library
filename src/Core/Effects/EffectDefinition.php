<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

class EffectDefinition
{
    /** @param EffectParameter[] $parameters */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly array  $parameters = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'label'       => $this->label,
            'description' => $this->description,
            'parameters'  => array_map(fn (EffectParameter $p) => $p->toArray(), $this->parameters),
        ];
    }
}
