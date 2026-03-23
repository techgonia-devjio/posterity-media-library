<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Core;

enum MediaType: string
{
    case Image    = 'image';
    case Video    = 'video';
    case Document = 'document';
    case Audio    = 'audio';
    case Unknown  = 'unknown';

    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/')       => self::Image,
            str_starts_with($mime, 'video/')       => self::Video,
            str_starts_with($mime, 'audio/')       => self::Audio,
            in_array($mime, self::documentMimes()) => self::Document,
            default                                => self::Unknown,
        };
    }

    private static function documentMimes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'application/rtf',
            'application/epub+zip',
        ];
    }
}
