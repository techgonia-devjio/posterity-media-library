<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\MediaType;

class AssetTest extends TestCase
{
    // ── Construction ──────────────────────────────────────────────────────────

    public function test_can_construct_with_required_fields(): void
    {
        $asset = new Asset(
            uuid:             'abc-123',
            path:             'uploads/photo.jpg',
            filename:         'photo.jpg',
            originalFilename: 'my-photo.jpg',
            extension:        'jpg',
            disk:             'public',
            type:             MediaType::Image,
        );

        $this->assertSame('abc-123', $asset->uuid);
        $this->assertSame('uploads/photo.jpg', $asset->path);
        $this->assertSame('photo.jpg', $asset->filename);
        $this->assertSame('my-photo.jpg', $asset->originalFilename);
        $this->assertSame('jpg', $asset->extension);
        $this->assertSame('public', $asset->disk);
        $this->assertSame(MediaType::Image, $asset->type);
    }

    public function test_defaults_are_applied_correctly(): void
    {
        $asset = $this->makeAsset();

        $this->assertSame([], $asset->metadata);
        $this->assertNull($asset->size);
        $this->assertNull($asset->mimeType);
        $this->assertSame(50, $asset->focusX);
        $this->assertSame(50, $asset->focusY);
        $this->assertNull($asset->thumbnail);
    }

    public function test_optional_fields_can_be_set(): void
    {
        $asset = new Asset(
            uuid:             'abc-123',
            path:             'uploads/photo.jpg',
            filename:         'photo.jpg',
            originalFilename: 'photo.jpg',
            extension:        'jpg',
            disk:             'public',
            type:             MediaType::Image,
            metadata:         ['title' => 'A photo'],
            size:             204800,
            mimeType:         'image/jpeg',
            focusX:           30,
            focusY:           70,
            thumbnail:        'uploads/.meta/photo_jpg/thumbnail.jpg',
        );

        $this->assertSame(['title' => 'A photo'], $asset->metadata);
        $this->assertSame(204800, $asset->size);
        $this->assertSame('image/jpeg', $asset->mimeType);
        $this->assertSame(30, $asset->focusX);
        $this->assertSame(70, $asset->focusY);
        $this->assertSame('uploads/.meta/photo_jpg/thumbnail.jpg', $asset->thumbnail);
    }

    // ── Type helpers ──────────────────────────────────────────────────────────

    public function test_is_image_returns_true_for_image_type(): void
    {
        $asset = $this->makeAsset(type: MediaType::Image);

        $this->assertTrue($asset->isImage());
        $this->assertFalse($asset->isVideo());
        $this->assertFalse($asset->isDocument());
        $this->assertFalse($asset->isAudio());
    }

    public function test_is_video_returns_true_for_video_type(): void
    {
        $asset = $this->makeAsset(type: MediaType::Video);

        $this->assertFalse($asset->isImage());
        $this->assertTrue($asset->isVideo());
        $this->assertFalse($asset->isDocument());
        $this->assertFalse($asset->isAudio());
    }

    public function test_is_document_returns_true_for_document_type(): void
    {
        $asset = $this->makeAsset(type: MediaType::Document);

        $this->assertFalse($asset->isImage());
        $this->assertFalse($asset->isVideo());
        $this->assertTrue($asset->isDocument());
        $this->assertFalse($asset->isAudio());
    }

    public function test_is_audio_returns_true_for_audio_type(): void
    {
        $asset = $this->makeAsset(type: MediaType::Audio);

        $this->assertFalse($asset->isImage());
        $this->assertFalse($asset->isVideo());
        $this->assertFalse($asset->isDocument());
        $this->assertTrue($asset->isAudio());
    }

    public function test_all_type_checks_false_for_unknown(): void
    {
        $asset = $this->makeAsset(type: MediaType::Unknown);

        $this->assertFalse($asset->isImage());
        $this->assertFalse($asset->isVideo());
        $this->assertFalse($asset->isDocument());
        $this->assertFalse($asset->isAudio());
    }

    // ── hasThumbnail ──────────────────────────────────────────────────────────

    public function test_has_thumbnail_false_when_null(): void
    {
        $asset = $this->makeAsset(thumbnail: null);
        $this->assertFalse($asset->hasThumbnail());
    }

    public function test_has_thumbnail_true_when_path_set(): void
    {
        $asset = $this->makeAsset(thumbnail: 'path/to/thumb.jpg');
        $this->assertTrue($asset->hasThumbnail());
    }

    // ── withMetadata (immutability) ────────────────────────────────────────────

    public function test_with_metadata_returns_new_instance(): void
    {
        $original = $this->makeAsset();
        $modified = $original->withMetadata('title', 'Hello');

        $this->assertNotSame($original, $modified);
        $this->assertSame([], $original->metadata);
        $this->assertSame(['title' => 'Hello'], $modified->metadata);
    }

    public function test_with_metadata_merges_keys(): void
    {
        $asset = $this->makeAsset();
        $asset = $asset->withMetadata('title', 'Hello');
        $asset = $asset->withMetadata('alt', 'World');

        $this->assertSame(['title' => 'Hello', 'alt' => 'World'], $asset->metadata);
    }

    public function test_with_metadata_overwrites_existing_key(): void
    {
        $asset  = $this->makeAsset(metadata: ['title' => 'Old']);
        $result = $asset->withMetadata('title', 'New');

        $this->assertSame('New', $result->metadata['title']);
        $this->assertSame('Old', $asset->metadata['title']); // original unchanged
    }

    public function test_with_metadata_supports_null_value(): void
    {
        $asset  = $this->makeAsset();
        $result = $asset->withMetadata('optional', null);

        $this->assertArrayHasKey('optional', $result->metadata);
        $this->assertNull($result->metadata['optional']);
    }

    // ── getTranslated ─────────────────────────────────────────────────────────

    public function test_get_translated_returns_string_value_directly(): void
    {
        $asset = $this->makeAsset(metadata: ['title' => 'Simple title']);

        $this->assertSame('Simple title', $asset->getTranslated('title'));
    }

    public function test_get_translated_returns_locale_from_array(): void
    {
        $asset = $this->makeAsset(metadata: [
            'title' => ['en' => 'English', 'de' => 'Deutsch'],
        ]);

        $this->assertSame('English', $asset->getTranslated('title', 'en'));
        $this->assertSame('Deutsch', $asset->getTranslated('title', 'de'));
    }

    public function test_get_translated_falls_back_to_en(): void
    {
        $asset = $this->makeAsset(metadata: [
            'title' => ['en' => 'English', 'de' => 'Deutsch'],
        ]);

        $this->assertSame('English', $asset->getTranslated('title', 'fr'));
    }

    public function test_get_translated_falls_back_to_first_value(): void
    {
        $asset = $this->makeAsset(metadata: [
            'title' => ['de' => 'Deutsch', 'fr' => 'Français'],
        ]);

        $this->assertSame('Deutsch', $asset->getTranslated('title', 'es'));
    }

    public function test_get_translated_returns_null_for_missing_field(): void
    {
        $asset = $this->makeAsset();

        $this->assertNull($asset->getTranslated('missing'));
    }

    public function test_get_translated_returns_null_for_non_string_non_array(): void
    {
        $asset = $this->makeAsset(metadata: ['count' => 42]);

        $this->assertNull($asset->getTranslated('count'));
    }

    // ── fromArray / toArray roundtrip ─────────────────────────────────────────

    public function test_from_array_roundtrip(): void
    {
        $data = [
            'uuid'              => 'abc-def-123',
            'path'              => 'gallery/photo.jpg',
            'filename'          => 'photo.jpg',
            'original_filename' => 'my photo.jpg',
            'extension'         => 'jpg',
            'disk'              => 's3',
            'type'              => 'image',
            'metadata'          => ['title' => 'My Photo'],
            'size'              => 102400,
            'mime_type'         => 'image/jpeg',
            'focus_x'           => 30,
            'focus_y'           => 60,
            'thumbnail'         => null,
        ];

        $asset = Asset::fromArray($data);

        $this->assertSame($data, $asset->toArray());
    }

    public function test_from_array_with_minimal_data_uses_defaults(): void
    {
        $data = [
            'uuid'              => 'abc',
            'path'              => 'file.jpg',
            'filename'          => 'file.jpg',
            'original_filename' => 'file.jpg',
            'extension'         => 'jpg',
            'disk'              => 'public',
            'type'              => 'image',
        ];

        $asset = Asset::fromArray($data);

        $this->assertSame([], $asset->metadata);
        $this->assertNull($asset->size);
        $this->assertNull($asset->mimeType);
        $this->assertSame(50, $asset->focusX);
        $this->assertSame(50, $asset->focusY);
        $this->assertNull($asset->thumbnail);
    }

    public function test_from_array_with_unknown_type(): void
    {
        $data = [
            'uuid'              => 'abc',
            'path'              => 'file.bin',
            'filename'          => 'file.bin',
            'original_filename' => 'file.bin',
            'extension'         => 'bin',
            'disk'              => 'public',
            'type'              => 'unknown',
        ];

        $asset = Asset::fromArray($data);

        $this->assertSame(MediaType::Unknown, $asset->type);
    }

    public function test_to_array_serialises_all_fields(): void
    {
        $asset  = $this->makeAsset(metadata: ['alt' => 'test'], size: 512, mimeType: 'image/jpeg');
        $result = $asset->toArray();

        $this->assertArrayHasKey('uuid', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('original_filename', $result);
        $this->assertArrayHasKey('extension', $result);
        $this->assertArrayHasKey('disk', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('mime_type', $result);
        $this->assertArrayHasKey('focus_x', $result);
        $this->assertArrayHasKey('focus_y', $result);
        $this->assertArrayHasKey('thumbnail', $result);
        $this->assertSame('image', $result['type']); // enum serialised as string
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeAsset(
        MediaType $type = MediaType::Image,
        array $metadata = [],
        ?int $size = null,
        ?string $mimeType = null,
        ?string $thumbnail = null,
    ): Asset {
        return new Asset(
            uuid:             'test-uuid',
            path:             'uploads/photo.jpg',
            filename:         'photo.jpg',
            originalFilename: 'photo.jpg',
            extension:        'jpg',
            disk:             'public',
            type:             $type,
            metadata:         $metadata,
            size:             $size,
            mimeType:         $mimeType,
            thumbnail:        $thumbnail,
        );
    }
}
