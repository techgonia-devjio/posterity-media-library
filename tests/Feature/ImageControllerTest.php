<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Posterity\MediaLibrary\Laravel\Facades\Media;
use Posterity\MediaLibrary\Support\UrlSigner;
use Posterity\MediaLibrary\Tests\TestCase;

class ImageControllerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-library.image.enabled', true);
        $app['config']->set('media-library.image.sign', false); // open by default
        $app['config']->set('media-library.image.middleware', []); // disable throttle in tests
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('app.key', 'base64:' . base64_encode('test-app-key-32-bytes-exactly!!'));
    }

    // ── Open endpoint (default) ───────────────────────────────────────────────

    public function test_it_serves_image_without_signature(): void
    {
        $file  = UploadedFile::fake()->image('open.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_it_rejects_unsigned_request_when_signing_is_on(): void
    {
        config(['media-library.image.sign' => true]);
        app()->forgetInstance('media-manager');

        $file  = UploadedFile::fake()->image('photo.jpg');
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200");

        $response->assertStatus(403);
    }

    public function test_it_accepts_signed_request_when_signing_is_on(): void
    {
        config(['media-library.image.sign' => true]);
        app()->forgetInstance('media-manager');

        $file  = UploadedFile::fake()->image('signed.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        $signer = app(UrlSigner::class);
        $params = ['path' => $asset->path, 'w' => 200];
        $sig    = $signer->sign($params);

        $response = $this->get("/img/{$asset->path}?w=200&sig={$sig}");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    // ── toImageUrl() ──────────────────────────────────────────────────────────

    public function test_to_image_url_contains_path(): void
    {
        $file  = UploadedFile::fake()->image('test.jpg');
        $asset = Media::upload($file, 'uploads');

        $url = Media::url($asset)->resize(400)->toImageUrl();

        $this->assertStringContainsString($asset->path, $url);
        $this->assertStringContainsString('w=400', $url);
        // No sig= when signing is off
        $this->assertStringNotContainsString('sig=', $url);
    }

    public function test_to_image_url_with_effect_preset(): void
    {
        $file  = UploadedFile::fake()->image('preset.jpg');
        $asset = Media::upload($file, 'uploads');

        $url = Media::url($asset)->preset('thumb')->toImageUrl();

        $this->assertStringContainsString('effect=thumb', $url);
    }

    public function test_image_srcset_contains_multiple_widths(): void
    {
        $file  = UploadedFile::fake()->image('srcset.jpg', 1280, 720);
        $asset = Media::upload($file, 'uploads');

        $srcset = Media::url($asset)->format('webp')->imageSrcset([320, 640, 1280]);

        $this->assertStringContainsString('320w', $srcset);
        $this->assertStringContainsString('640w', $srcset);
        $this->assertStringContainsString('1280w', $srcset);
    }

    // ── HTTP endpoint ─────────────────────────────────────────────────────────

    public function test_it_processes_image_via_path_url(): void
    {
        $file  = UploadedFile::fake()->image('hero.jpg', 1280, 720);
        $asset = Media::upload($file, 'uploads');

        $url = Media::url($asset)->resize(400)->format('webp')->toImageUrl();

        $parsed   = parse_url($url);
        $endpoint = $parsed['path'] . '?' . $parsed['query'];

        $response = $this->get($endpoint);

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_it_caches_the_result(): void
    {
        $file  = UploadedFile::fake()->image('cache.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        $url    = Media::url($asset)->resize(300)->toImageUrl();
        $parsed = parse_url($url);
        $path   = $parsed['path'] . '?' . $parsed['query'];

        $this->get($path); // first request — process + cache

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);

        $this->get($path); // second request — cache hit

        $filesAfter = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');
        $this->assertCount(1, $filesAfter); // still just one file
    }

    public function test_it_applies_effect_cover_from_url(): void
    {
        $file  = UploadedFile::fake()->image('cover.jpg', 1000, 1000);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200&h=200&effect=cover");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_it_applies_named_preset_via_effect_param(): void
    {
        $file  = UploadedFile::fake()->image('thumb.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?effect=thumb");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);

        // Cached file should be .webp (thumb preset outputs webp)
        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.webp', $files[0]);
    }

    public function test_it_returns_404_for_nonexistent_path(): void
    {
        $response = $this->get('/img/uploads/nonexistent.jpg?w=200');

        $response->assertStatus(404);
    }
}
