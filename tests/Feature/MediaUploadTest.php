<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Posterity\MediaLibrary\Core\MediaType;
use Posterity\MediaLibrary\Laravel\Facades\Media;
use Posterity\MediaLibrary\Laravel\MediaManager;
use Posterity\MediaLibrary\Tests\TestCase;

class MediaUploadTest extends TestCase
{
    // ── Upload & sidecar ──────────────────────────────────────────────────────

    public function test_it_can_upload_a_file_and_creates_a_sidecar(): void
    {
        $file  = UploadedFile::fake()->image('nature.jpg', 640, 480);
        $asset = Media::upload($file, 'gallery');

        // Original file on disk
        Storage::disk('public')->assertExists($asset->path);

        // Sidecar JSON
        $dir     = dirname($asset->path);
        $slug    = str_replace('.', '_', basename($asset->path));
        $base    = $dir === '.' ? '' : $dir . '/';
        $metaPath = $base . '.meta/' . $slug . '/metadata.json';

        Storage::disk('public')->assertExists($metaPath);
    }

    public function test_it_stores_a_uuid_in_the_sidecar(): void
    {
        $file  = UploadedFile::fake()->image('profile.jpg');
        $asset = Media::upload($file, 'uploads');

        $this->assertNotEmpty($asset->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $asset->uuid,
        );
    }

    public function test_it_detects_image_media_type(): void
    {
        $file  = UploadedFile::fake()->image('photo.jpg');
        $asset = Media::upload($file);

        $this->assertSame(MediaType::Image, $asset->type);
        $this->assertTrue($asset->isImage());
    }

    // ── Retrieve & delete ─────────────────────────────────────────────────────

    public function test_it_can_retrieve_an_asset_by_path(): void
    {
        $file  = UploadedFile::fake()->image('retrieve.jpg');
        $asset = Media::upload($file, 'gallery');

        $fetched = Media::get($asset->path);

        $this->assertNotNull($fetched);
        $this->assertSame($asset->uuid, $fetched->uuid);
    }

    public function test_it_can_find_an_asset_by_uuid(): void
    {
        $file  = UploadedFile::fake()->image('find.jpg');
        $asset = Media::upload($file, 'gallery');

        $found = Media::findByUuid($asset->uuid);

        $this->assertNotNull($found);
        $this->assertSame($asset->uuid, $found->uuid);
    }

    public function test_it_can_delete_an_asset_and_its_sidecar(): void
    {
        $file  = UploadedFile::fake()->image('delete.jpg');
        $asset = Media::upload($file, 'gallery');

        $dir     = dirname($asset->path);
        $slug    = str_replace('.', '_', basename($asset->path));
        $base    = $dir === '.' ? '' : $dir . '/';
        $metaDir = $base . '.meta/' . $slug;

        Media::delete($asset->path);

        Storage::disk('public')->assertMissing($asset->path);
        Storage::disk('public')->assertMissing($metaDir . '/metadata.json');
    }

    // ── Image processing ──────────────────────────────────────────────────────

    public function test_it_generates_a_cached_resized_image(): void
    {
        $file  = UploadedFile::fake()->image('large.jpg', 800, 600);
        $asset = Media::upload($file, 'gallery');

        $url = Media::url($asset)->resize(200)->toUrl();

        $this->assertStringContainsString('/cache/', $url);

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);
    }

    public function test_it_caches_the_same_operation_only_once(): void
    {
        $file  = UploadedFile::fake()->image('cache.jpg', 800, 600);
        $asset = Media::upload($file, 'gallery');

        Media::url($asset)->resize(200)->toUrl();
        Media::url($asset)->resize(200)->toUrl(); // second call — should hit cache

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files); // still only one cached file
    }

    public function test_it_applies_presets_from_yaml(): void
    {
        $file  = UploadedFile::fake()->image('preset.jpg', 640, 480);
        $asset = Media::upload($file, 'gallery');

        $url = Media::url($asset)->preset('thumb')->toUrl();

        $this->assertStringEndsWith('.webp', $url);
    }

    public function test_it_uses_focus_point_for_cover_crop(): void
    {
        $file  = UploadedFile::fake()->image('focus.jpg', 1000, 1000);
        $asset = Media::upload($file, 'gallery');

        $asset->focusX = 25;
        $asset->focusY = 75;
        Media::save($asset);

        // cover() will use the asset's saved focus point
        $url = Media::url($asset)->cover(200, 200)->toUrl();

        $this->assertNotEmpty($url);
    }

    public function test_it_generates_a_srcset_string(): void
    {
        $file  = UploadedFile::fake()->image('srcset.jpg', 1280, 720);
        $asset = Media::upload($file, 'gallery');

        $srcset = Media::url($asset)->format('webp')->srcset([320, 640, 1280]);

        $this->assertStringContainsString('320w', $srcset);
        $this->assertStringContainsString('640w', $srcset);
        $this->assertStringContainsString('1280w', $srcset);
    }

    // ── Multi-disk ────────────────────────────────────────────────────────────

    public function test_it_stores_processed_images_on_a_separate_cache_disk(): void
    {
        Storage::fake('s3');
        config(['media-library.cache_disk' => 's3']);

        /** @var MediaManager $manager */
        $manager = app('media-manager');

        $file  = UploadedFile::fake()->image('cloud.jpg', 640, 480);
        $asset = $manager->upload($file, 'gallery');

        $manager->url($asset)->resize(100)->toUrl();

        Storage::disk('public')->assertExists($asset->path);

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('s3')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);
    }
}
