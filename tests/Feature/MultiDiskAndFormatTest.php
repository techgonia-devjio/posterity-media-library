<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Posterity\MediaLibrary\Laravel\Facades\Media;
use Posterity\MediaLibrary\Tests\TestCase;

class MultiDiskAndFormatTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-library.image.enabled', true);
        $app['config']->set('media-library.image.sign', false);
        $app['config']->set('media-library.image.middleware', []);
        $app['config']->set('app.url', 'http://localhost');

        $app['config']->set('filesystems.disks.secondary', [
            'driver' => 'local',
            'root'   => storage_path('app/secondary'),
            'url'    => 'http://localhost/secondary',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secondary');
    }

    // ── Multi-disk originals ──────────────────────────────────────────────────

    public function test_upload_to_non_default_disk_stores_on_that_disk(): void
    {
        $file  = UploadedFile::fake()->image('secondary.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        $this->assertSame('secondary', $asset->disk);
        Storage::disk('secondary')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_url_for_non_default_disk_asset_contains_path(): void
    {
        $file  = UploadedFile::fake()->image('sec_url.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        $url = Media::url($asset)->resize(200)->toImageUrl();

        $this->assertStringContainsString($asset->path, $url);
        $this->assertStringContainsString('w=200', $url);
    }

    public function test_process_and_store_reads_from_non_default_disk(): void
    {
        $file  = UploadedFile::fake()->image('sec_process.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        $generator = Media::url($asset)->resize(200);
        $cachePath = $generator->processAndStore();

        $this->assertNotEmpty($cachePath);
        Storage::disk('public')->assertExists($cachePath);
    }

    public function test_repeated_processing_of_non_default_disk_asset_hits_cache(): void
    {
        $file  = UploadedFile::fake()->image('sec_cache.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        $generator = Media::url($asset)->resize(200)->format('webp');
        $generator->processAndStore();
        $generator->processAndStore(); // second call must not throw

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.webp', $files[0]);
    }

    public function test_find_by_uuid_with_explicit_disk_resolves_non_default_disk(): void
    {
        $file  = UploadedFile::fake()->image('sec_uuid.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        $found = Media::findByUuid($asset->uuid, 'secondary');

        $this->assertNotNull($found);
        $this->assertSame($asset->uuid, $found->uuid);
        $this->assertSame('secondary', $found->disk);
    }

    public function test_find_by_uuid_on_wrong_disk_returns_null(): void
    {
        $file  = UploadedFile::fake()->image('sec_wrongdisk.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        // Asset is on 'secondary'; looking it up on default disk ('public') returns null
        $found = Media::findByUuid($asset->uuid, 'public');

        $this->assertNull($found);
    }

    public function test_delete_removes_asset_from_non_default_disk(): void
    {
        $file  = UploadedFile::fake()->image('sec_del.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads', 'secondary');

        Storage::disk('secondary')->assertExists($asset->path);

        Media::delete($asset->path, 'secondary');

        Storage::disk('secondary')->assertMissing($asset->path);
    }

    // ── Invalid format — HTTP ─────────────────────────────────────────────────

    public function test_invalid_fmt_bmp_returns_422(): void
    {
        $file  = UploadedFile::fake()->image('fmt422.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?fmt=bmp");

        $response->assertStatus(422);
    }

    public function test_invalid_fmt_tiff_returns_422(): void
    {
        $file  = UploadedFile::fake()->image('tiff422.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?fmt=tiff");

        $response->assertStatus(422);
    }

    public function test_invalid_fmt_svg_returns_422(): void
    {
        $file  = UploadedFile::fake()->image('svg422.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?fmt=svg");

        $response->assertStatus(422);
    }

    public function test_valid_fmt_webp_does_not_return_422(): void
    {
        $file  = UploadedFile::fake()->image('fmtok.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?fmt=webp");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_valid_fmt_png_does_not_return_422(): void
    {
        $file  = UploadedFile::fake()->image('pngok.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?fmt=png");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_valid_fmt_avif_is_not_rejected_as_invalid_format(): void
    {
        $file  = UploadedFile::fake()->image('aviftest.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?fmt=avif");

        // avif is a supported format name — must not return 422 (validation rejection)
        // May return 500 on servers where GD lacks AVIF support
        $this->assertNotSame(422, $response->getStatusCode());
    }

    // ── Invalid format — PHP API ──────────────────────────────────────────────

    public function test_url_generator_format_throws_for_bmp(): void
    {
        $file  = UploadedFile::fake()->image('phpfmt.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $this->expectException(InvalidArgumentException::class);

        Media::url($asset)->format('bmp');
    }

    public function test_url_generator_format_throws_for_tiff(): void
    {
        $file  = UploadedFile::fake()->image('phpfmt2.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $this->expectException(InvalidArgumentException::class);

        Media::url($asset)->format('tiff');
    }

    public function test_url_generator_format_throws_for_svg(): void
    {
        $file  = UploadedFile::fake()->image('phpfmt3.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $this->expectException(InvalidArgumentException::class);

        Media::url($asset)->format('svg');
    }

    public function test_url_generator_format_accepts_all_supported_types(): void
    {
        $file  = UploadedFile::fake()->image('phpfmtok.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        foreach (['jpg', 'jpeg', 'webp', 'avif', 'png', 'gif'] as $fmt) {
            $caught = false;
            try {
                Media::url($asset)->format($fmt);
            } catch (InvalidArgumentException) {
                $caught = true;
            }
            $this->assertFalse($caught, "format('{$fmt}') must not throw InvalidArgumentException");
        }
    }
}
