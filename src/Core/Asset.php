<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core;

class Asset
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $path,
        public readonly string $filename,
        public readonly string $originalFilename,
        public readonly string $extension,
        public readonly string $disk,
        public readonly MediaType $type,
        public array $metadata = [],
        public ?int $size = null,
        public ?string $mimeType = null,
        public int $focusX = 50,
        public int $focusY = 50,
        public ?string $thumbnail = null,
    ) {}

    public function isImage(): bool
    {
        return $this->type === MediaType::Image;
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::Video;
    }

    public function isDocument(): bool
    {
        return $this->type === MediaType::Document;
    }

    public function isAudio(): bool
    {
        return $this->type === MediaType::Audio;
    }

    public function hasThumbnail(): bool
    {
        return $this->thumbnail !== null;
    }

    public function withMetadata(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;

        return $clone;
    }

    public function getTranslated(string $field, string $locale = 'en'): ?string
    {
        $value = $this->metadata[$field] ?? null;

        if (is_array($value)) {
            return $value[$locale] ?? $value['en'] ?? array_values($value)[0] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uuid:             $data['uuid'],
            path:             $data['path'],
            filename:         $data['filename'],
            originalFilename: $data['original_filename'],
            extension:        $data['extension'],
            disk:             $data['disk'],
            type:             MediaType::from($data['type'] ?? MediaType::Unknown->value),
            metadata:         $data['metadata'] ?? [],
            size:             $data['size'] ?? null,
            mimeType:         $data['mime_type'] ?? null,
            focusX:           $data['focus_x'] ?? 50,
            focusY:           $data['focus_y'] ?? 50,
            thumbnail:        $data['thumbnail'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'uuid'              => $this->uuid,
            'path'              => $this->path,
            'filename'          => $this->filename,
            'original_filename' => $this->originalFilename,
            'extension'         => $this->extension,
            'disk'              => $this->disk,
            'type'              => $this->type->value,
            'metadata'          => $this->metadata,
            'size'              => $this->size,
            'mime_type'         => $this->mimeType,
            'focus_x'           => $this->focusX,
            'focus_y'           => $this->focusY,
            'thumbnail'         => $this->thumbnail,
        ];
    }
}
