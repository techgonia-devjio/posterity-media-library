<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Posterity\MediaLibrary\Core\MediaType;
use Posterity\MediaLibrary\Laravel\Facades\Media;
use Posterity\MediaLibrary\Tests\TestCase;

class MediaUploadExtendedTest extends TestCase
{
    // ── Non-image uploads ─────────────────────────────────────────────────────

    public function test_pdf_upload_is_detected_as_document_type(): void
    {
        $file  = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        $asset = Media::upload($file, 'documents');

        $this->assertSame(MediaType::Document, $asset->type);
        $this->assertTrue($asset->isDocument());
        $this->assertFalse($asset->isImage());
    }

    public function test_mp4_upload_is_detected_as_video_type(): void
    {
        $file  = UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4');
        $asset = Media::upload($file, 'videos');

        $this->assertSame(MediaType::Video, $asset->type);
        $this->assertTrue($asset->isVideo());
    }

    public function test_mp3_upload_is_detected_as_audio_type(): void
    {
        $file  = UploadedFile::fake()->create('song.mp3', 300, 'audio/mpeg');
        $asset = Media::upload($file, 'audio');

        $this->assertSame(MediaType::Audio, $asset->type);
        $this->assertTrue($asset->isAudio());
    }

    public function test_png_upload_stores_correct_extension(): void
    {
        $file  = UploadedFile::fake()->image('icon.png', 64, 64);
        $asset = Media::upload($file, 'icons');

        $this->assertSame('png', $asset->extension);
        $this->assertStringEndsWith('.png', $asset->path);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_upload_creates_sidecar_at_correct_path(): void
    {
        $file  = UploadedFile::fake()->image('root.jpg', 100, 100);
        $asset = Media::upload($file, 'uploads');

        Storage::disk('public')->assertExists($asset->path);

        $dir     = dirname($asset->path);
        $slug    = str_replace('.', '_', basename($asset->path));
        $base    = $dir === '.' ? '' : $dir . '/';
        $metaPath = $base . '.meta/' . $slug . '/metadata.json';

        Storage::disk('public')->assertExists($metaPath);
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    public function test_save_updates_metadata_on_disk(): void
    {
        $file  = UploadedFile::fake()->image('update.jpg');
        $asset = Media::upload($file, 'uploads');

        $asset->metadata['alt'] = 'An updated caption';
        Media::save($asset);

        $fetched = Media::get($asset->path);

        $this->assertNotNull($fetched);
        $this->assertSame('An updated caption', $fetched->metadata['alt']);
    }

    public function test_save_updates_focus_point(): void
    {
        $file  = UploadedFile::fake()->image('focus.jpg', 800, 600);
        $asset = Media::upload($file, 'uploads');

        $asset->focusX = 20;
        $asset->focusY = 80;
        Media::save($asset);

        $fetched = Media::get($asset->path);

        $this->assertNotNull($fetched);
        $this->assertSame(20, $fetched->focusX);
        $this->assertSame(80, $fetched->focusY);
    }

    public function test_with_metadata_sets_multilingual_field(): void
    {
        $file  = UploadedFile::fake()->image('ml.jpg');
        $asset = Media::upload($file, 'uploads');

        $updated = $asset->withMetadata('title', ['en' => 'Hello', 'de' => 'Hallo']);
        Media::save($updated);

        $fetched = Media::get($asset->path);

        $this->assertNotNull($fetched);
        $this->assertSame('Hello', $fetched->getTranslated('title', 'en'));
        $this->assertSame('Hallo', $fetched->getTranslated('title', 'de'));
    }

    public function test_asset_preserves_original_filename_separately(): void
    {
        $file  = UploadedFile::fake()->image('my-custom-photo.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $this->assertSame('my-custom-photo.jpg', $asset->originalFilename);
        // Stored filename is UUID-based and differs from original
        $this->assertNotSame($asset->originalFilename, $asset->filename);
    }

    // ── Retrieval ─────────────────────────────────────────────────────────────

    public function test_get_returns_null_for_nonexistent_path(): void
    {
        $result = Media::get('uploads/does-not-exist.jpg');

        $this->assertNull($result);
    }

    public function test_find_by_uuid_returns_null_for_nonexistent_uuid(): void
    {
        $result = Media::findByUuid('00000000-0000-4000-8000-000000000000');

        $this->assertNull($result);
    }

    public function test_find_by_uuid_works_with_multiple_assets(): void
    {
        $file1  = UploadedFile::fake()->image('a.jpg');
        $file2  = UploadedFile::fake()->image('b.jpg');
        $file3  = UploadedFile::fake()->image('c.jpg');

        $asset1 = Media::upload($file1, 'uploads');
        $asset2 = Media::upload($file2, 'uploads');
        $asset3 = Media::upload($file3, 'uploads');

        $found = Media::findByUuid($asset2->uuid);

        $this->assertNotNull($found);
        $this->assertSame($asset2->uuid, $found->uuid);
    }

    // ── Deletion ──────────────────────────────────────────────────────────────

    public function test_delete_removes_cached_variants(): void
    {
        $file  = UploadedFile::fake()->image('cached.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        // Generate a cached variant
        Media::url($asset)->resize(200)->toUrl();

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $metaDir = $base . '.meta/' . $slug;

        // Verify cache was created
        $files = Storage::disk('public')->files($metaDir . '/cache');
        $this->assertNotEmpty($files);

        // Delete asset
        Media::delete($asset->path);

        Storage::disk('public')->assertMissing($asset->path);
        // Meta directory (including cache) should be gone
        Storage::disk('public')->assertMissing($metaDir . '/metadata.json');
    }

    // ── UrlGenerator edge cases ───────────────────────────────────────────────

    public function test_to_url_with_no_effects_returns_storage_url_of_original(): void
    {
        $file  = UploadedFile::fake()->image('original.jpg');
        $asset = Media::upload($file, 'uploads');

        $url = Media::url($asset)->toUrl();

        // No effects = no processing = original file URL
        $this->assertStringContainsString($asset->path, $url);

        $dir  = dirname($asset->path);
        $slug = str_replace('.', '_', basename($asset->path));
        $base = $dir === '.' ? '' : $dir . '/';

        // No cache file should exist
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');
        $this->assertEmpty($files);
    }

    public function test_srcset_generates_correct_number_of_entries(): void
    {
        $file   = UploadedFile::fake()->image('srcset.jpg', 1920, 1080);
        $asset  = Media::upload($file, 'uploads');
        $widths = [320, 640, 960, 1280, 1920];

        $srcset = Media::url($asset)->srcset($widths);
        $parts  = explode(', ', $srcset);

        $this->assertCount(5, $parts);
    }

    public function test_srcset_each_entry_has_width_descriptor(): void
    {
        $file   = UploadedFile::fake()->image('srcset.jpg', 1280, 720);
        $asset  = Media::upload($file, 'uploads');
        $widths = [320, 640, 1280];

        $srcset = Media::url($asset)->format('webp')->srcset($widths);

        foreach ($widths as $w) {
            $this->assertStringContainsString("{$w}w", $srcset);
        }
    }

    public function test_to_string_is_alias_for_to_url(): void
    {
        $file  = UploadedFile::fake()->image('str.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $gen = Media::url($asset)->resize(200);

        $this->assertSame($gen->toUrl(), (string) $gen);
    }

    public function test_cover_uses_saved_focus_point_from_asset(): void
    {
        $file  = UploadedFile::fake()->image('focus.jpg', 1000, 1000);
        $asset = Media::upload($file, 'uploads');

        $asset->focusX = 10;
        $asset->focusY = 10;
        Media::save($asset);

        $urlA = Media::url($asset)->cover(300, 300)->getCachePath();

        // Change focus and get cache path — should be different
        $asset->focusX = 90;
        $asset->focusY = 90;
        Media::save($asset);

        $urlB = Media::url($asset)->cover(300, 300)->getCachePath();

        $this->assertNotSame($urlA, $urlB);
    }

    public function test_different_effects_produce_different_cache_paths(): void
    {
        $file  = UploadedFile::fake()->image('paths.jpg', 800, 600);
        $asset = Media::upload($file, 'uploads');

        $resize  = Media::url($asset)->resize(200)->getCachePath();
        $blur    = Media::url($asset)->blur(10)->getCachePath();
        $webp    = Media::url($asset)->format('webp')->getCachePath();
        $resize2 = Media::url($asset)->resize(400)->getCachePath();

        $this->assertNotSame($resize, $blur);
        $this->assertNotSame($resize, $webp);
        $this->assertNotSame($resize, $resize2);
    }
}
