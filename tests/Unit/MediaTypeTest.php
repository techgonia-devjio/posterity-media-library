<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Core\MediaType;

class MediaTypeTest extends TestCase
{
    // ── Image MIME types ──────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('imageMimes')]
    public function test_image_mimes_resolve_to_image(string $mime): void
    {
        $this->assertSame(MediaType::Image, MediaType::fromMime($mime));
    }

    public static function imageMimes(): array
    {
        return [
            'jpeg'  => ['image/jpeg'],
            'png'   => ['image/png'],
            'webp'  => ['image/webp'],
            'gif'   => ['image/gif'],
            'avif'  => ['image/avif'],
            'svg'   => ['image/svg+xml'],
            'bmp'   => ['image/bmp'],
            'tiff'  => ['image/tiff'],
            'ico'   => ['image/x-icon'],
        ];
    }

    // ── Video MIME types ──────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('videoMimes')]
    public function test_video_mimes_resolve_to_video(string $mime): void
    {
        $this->assertSame(MediaType::Video, MediaType::fromMime($mime));
    }

    public static function videoMimes(): array
    {
        return [
            'mp4'  => ['video/mp4'],
            'webm' => ['video/webm'],
            'ogg'  => ['video/ogg'],
            'mov'  => ['video/quicktime'],
            'avi'  => ['video/x-msvideo'],
        ];
    }

    // ── Audio MIME types ──────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('audioMimes')]
    public function test_audio_mimes_resolve_to_audio(string $mime): void
    {
        $this->assertSame(MediaType::Audio, MediaType::fromMime($mime));
    }

    public static function audioMimes(): array
    {
        return [
            'mpeg' => ['audio/mpeg'],
            'wav'  => ['audio/wav'],
            'ogg'  => ['audio/ogg'],
            'flac' => ['audio/flac'],
            'mp4a' => ['audio/mp4'],
        ];
    }

    // ── Document MIME types ───────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('documentMimes')]
    public function test_document_mimes_resolve_to_document(string $mime): void
    {
        $this->assertSame(MediaType::Document, MediaType::fromMime($mime));
    }

    public static function documentMimes(): array
    {
        return [
            'pdf'       => ['application/pdf'],
            'doc'       => ['application/msword'],
            'docx'      => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls'       => ['application/vnd.ms-excel'],
            'xlsx'      => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt'       => ['application/vnd.ms-powerpoint'],
            'pptx'      => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt'       => ['text/plain'],
            'csv'       => ['text/csv'],
            'rtf'       => ['application/rtf'],
            'epub'      => ['application/epub+zip'],
        ];
    }

    // ── Unknown MIME types ────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownMimes')]
    public function test_unknown_mimes_resolve_to_unknown(string $mime): void
    {
        $this->assertSame(MediaType::Unknown, MediaType::fromMime($mime));
    }

    public static function unknownMimes(): array
    {
        return [
            'binary'      => ['application/octet-stream'],
            'empty'       => [''],
            'zip'         => ['application/zip'],
            'json'        => ['application/json'],
            'xml'         => ['application/xml'],
            'html'        => ['text/html'],
            'totally-made-up' => ['x-custom/something'],
        ];
    }

    // ── Enum values ───────────────────────────────────────────────────────────

    public function test_enum_backing_values_are_strings(): void
    {
        $this->assertSame('image',    MediaType::Image->value);
        $this->assertSame('video',    MediaType::Video->value);
        $this->assertSame('document', MediaType::Document->value);
        $this->assertSame('audio',    MediaType::Audio->value);
        $this->assertSame('unknown',  MediaType::Unknown->value);
    }

    public function test_from_string_value_works_for_all_cases(): void
    {
        $this->assertSame(MediaType::Image,    MediaType::from('image'));
        $this->assertSame(MediaType::Video,    MediaType::from('video'));
        $this->assertSame(MediaType::Document, MediaType::from('document'));
        $this->assertSame(MediaType::Audio,    MediaType::from('audio'));
        $this->assertSame(MediaType::Unknown,  MediaType::from('unknown'));
    }

    public function test_from_mime_is_case_sensitive(): void
    {
        // MIME types are conventionally lowercase; uppercase should be unknown
        $this->assertSame(MediaType::Unknown, MediaType::fromMime('IMAGE/JPEG'));
        $this->assertSame(MediaType::Unknown, MediaType::fromMime('Video/mp4'));
    }

    public function test_image_prefix_match_does_not_bleed_into_others(): void
    {
        // 'video/...' should not be caught by 'image/' prefix check
        $this->assertSame(MediaType::Video, MediaType::fromMime('video/mp4'));
        $this->assertNotSame(MediaType::Image, MediaType::fromMime('video/mp4'));
    }
}
