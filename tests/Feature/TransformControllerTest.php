<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Posterity\MediaLibrary\Laravel\Facades\Media;
use Posterity\MediaLibrary\Support\UrlSigner;
use Posterity\MediaLibrary\Tests\TestCase;

class TransformControllerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-library.transform.enabled', true);
        $app['config']->set('app.url', 'http://localhost');
    }

    // ── Signature verification ────────────────────────────────────────────────

    public function test_it_rejects_unsigned_requests(): void
    {
        $file  = UploadedFile::fake()->image('photo.jpg');
        $asset = Media::upload($file, 'gallery');

        $response = $this->get("/media/img/{$asset->uuid}?w=200");

        $response->assertStatus(403);
    }

    public function test_it_rejects_tampered_requests(): void
    {
        $file   = UploadedFile::fake()->image('photo.jpg');
        $asset  = Media::upload($file, 'gallery');
        $signer = app(UrlSigner::class);

        $params = ['uuid' => $asset->uuid, 'w' => 200];
        $sig    = $signer->sign($params);

        // Tamper: change width after signing
        $response = $this->get("/media/img/{$asset->uuid}?w=800&sig={$sig}");

        $response->assertStatus(403);
    }

    // ── Successful transforms ─────────────────────────────────────────────────

    public function test_it_returns_a_response_for_valid_signed_request(): void
    {
        $file  = UploadedFile::fake()->image('photo.jpg', 640, 480);
        $asset = Media::upload($file, 'gallery');

        $signedUrl = Media::url($asset)->resize(200)->toSignedUrl();

        // Extract path + query from the signed URL
        $parsed = parse_url($signedUrl);
        $path   = $parsed['path'] . '?' . $parsed['query'];

        $response = $this->get($path);

        // Either a redirect (public disk with http URL) or streamed image
        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_it_caches_the_processed_image(): void
    {
        $file  = UploadedFile::fake()->image('cache_test.jpg', 640, 480);
        $asset = Media::upload($file, 'gallery');

        $signedUrl = Media::url($asset)->resize(300)->toSignedUrl();
        $parsed    = parse_url($signedUrl);
        $path      = $parsed['path'] . '?' . $parsed['query'];

        // First request — processes and caches
        $this->get($path);

        // Verify cache file was created
        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);

        // Second request — served from cache (same number of cached files)
        $this->get($path);

        $filesAfter = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');
        $this->assertCount(1, $filesAfter);
    }

    public function test_it_processes_a_webp_format_request(): void
    {
        $file  = UploadedFile::fake()->image('webp_test.jpg', 640, 480);
        $asset = Media::upload($file, 'gallery');

        $signedUrl = Media::url($asset)->resize(200)->format('webp', 80)->toSignedUrl();
        $parsed    = parse_url($signedUrl);
        $path      = $parsed['path'] . '?' . $parsed['query'];

        $this->get($path);

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.webp', $files[0]);
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $signer = app(UrlSigner::class);
        $uuid   = '00000000-0000-4000-8000-000000000000';
        $params = ['uuid' => $uuid, 'w' => 200];
        $sig    = $signer->sign($params);

        $response = $this->get("/media/img/{$uuid}?w=200&sig={$sig}");

        $response->assertStatus(404);
    }

    // ── toSignedUrl API ───────────────────────────────────────────────────────

    public function test_to_signed_url_contains_uuid_in_path(): void
    {
        $file  = UploadedFile::fake()->image('signed.jpg');
        $asset = Media::upload($file, 'gallery');

        $signed = Media::url($asset)->resize(400)->toSignedUrl();

        $this->assertStringContainsString($asset->uuid, $signed);
    }

    public function test_to_signed_url_contains_sig_parameter(): void
    {
        $file  = UploadedFile::fake()->image('signed2.jpg');
        $asset = Media::upload($file, 'gallery');

        $signed = Media::url($asset)->resize(400)->toSignedUrl();

        $this->assertStringContainsString('sig=', $signed);
    }

    public function test_signed_srcset_contains_multiple_widths(): void
    {
        $file  = UploadedFile::fake()->image('srcset.jpg', 1280, 720);
        $asset = Media::upload($file, 'gallery');

        $srcset = Media::url($asset)->format('webp')->signedSrcset([320, 640, 1280]);

        $this->assertStringContainsString('320w', $srcset);
        $this->assertStringContainsString('640w', $srcset);
        $this->assertStringContainsString('1280w', $srcset);
    }
}
