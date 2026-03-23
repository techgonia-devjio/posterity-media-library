<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core\Effects;

class EffectParameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,      // 'integer' | 'select' | 'boolean' | 'string'
        public readonly string $label,
        public readonly mixed  $default = null,
        public readonly ?int   $min = null,
        public readonly ?int   $max = null,
        public readonly array  $options = [],  // for 'select' type: ['value' => 'Label']
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name'    => $this->name,
            'type'    => $this->type,
            'label'   => $this->label,
            'default' => $this->default,
            'min'     => $this->min,
            'max'     => $this->max,
            'options' => $this->options ?: null,
        ], fn ($v) => $v !== null);
    }
}
