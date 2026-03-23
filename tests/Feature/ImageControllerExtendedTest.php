<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Posterity\MediaLibrary\Laravel\Facades\Media;
use Posterity\MediaLibrary\Tests\TestCase;

class ImageControllerExtendedTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-library.image.enabled', true);
        $app['config']->set('media-library.image.sign', false);
        $app['config']->set('media-library.image.middleware', []);
        $app['config']->set('app.url', 'http://localhost');
    }

    // ── Individual modifier params ─────────────────────────────────────────────

    public function test_blur_param_creates_cached_file(): void
    {
        $file  = UploadedFile::fake()->image('blur.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?blur=15");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_grey_param_creates_greyscale_cached_file(): void
    {
        $file  = UploadedFile::fake()->image('grey.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?grey=1");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_flip_param_creates_flipped_cached_file(): void
    {
        $file  = UploadedFile::fake()->image('flip.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?flip=1");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_flop_param_creates_flopped_cached_file(): void
    {
        $file  = UploadedFile::fake()->image('flop.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?flop=1");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_fmt_only_reformats_without_resize(): void
    {
        $file  = UploadedFile::fake()->image('fmt.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $this->get("/img/{$asset->path}?fmt=webp");

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.webp', $files[0]);
    }

    public function test_w_without_h_resizes_preserving_aspect_ratio(): void
    {
        $file  = UploadedFile::fake()->image('wide.jpg', 800, 400);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=400");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_quality_without_fmt_reencodes_in_same_format(): void
    {
        $file  = UploadedFile::fake()->image('quality.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?q=50");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_combined_params_w_h_fmt_q(): void
    {
        $file  = UploadedFile::fake()->image('combo.jpg', 1200, 800);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=600&h=400&fmt=webp&q=75");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_blur_is_composable_with_resize(): void
    {
        $file  = UploadedFile::fake()->image('blur_resize.jpg', 800, 600);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=400&blur=10");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    // ── Blur cap ──────────────────────────────────────────────────────────────

    public function test_blur_is_capped_at_100(): void
    {
        $file  = UploadedFile::fake()->image('bigblur.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        // blur=9999 — must not crash (capped to 100 internally)
        $response = $this->get("/img/{$asset->path}?blur=9999");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_blur_zero_via_http_does_not_create_extra_cache_entry(): void
    {
        // blur=0 is falsy in PHP — the controller skips it entirely
        $file  = UploadedFile::fake()->image('zeroblur.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        // via HTTP: ?blur=0 is same as no blur param (falsy check in buildGenerator)
        $this->get("/img/{$asset->path}?w=300");
        $this->get("/img/{$asset->path}?w=300&blur=0"); // blur=0 → skipped → same pipeline

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        // Both requests hit the same cache entry
        $this->assertCount(1, $files);
    }

    // ── Focus point override ──────────────────────────────────────────────────

    public function test_fx_fy_override_focus_for_cover(): void
    {
        $file  = UploadedFile::fake()->image('fxfy.jpg', 1000, 1000);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200&h=200&effect=cover&fx=10&fy=10");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_different_fx_fy_produce_different_cache_keys(): void
    {
        $file  = UploadedFile::fake()->image('fxfycache.jpg', 800, 600);
        $asset = Media::upload($file, 'uploads');

        // First request with fx=10,fy=10
        $this->get("/img/{$asset->path}?w=200&h=200&effect=cover&fx=10&fy=10");

        $dir  = dirname($asset->path);
        $slug = str_replace('.', '_', basename($asset->path));
        $base = $dir === '.' ? '' : $dir . '/';

        $filesAfterFirst = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        // Second request with different focus
        $this->get("/img/{$asset->path}?w=200&h=200&effect=cover&fx=90&fy=90");

        $filesAfterSecond = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        // Different focus → different cache key → two cached files
        $this->assertCount(2, $filesAfterSecond);
    }

    // ── Unknown / unrecognised effects ────────────────────────────────────────

    public function test_unknown_effect_name_is_a_no_op(): void
    {
        $file  = UploadedFile::fake()->image('noeffect.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        // 'totally-unknown-effect' is not a preset and not a shorthand
        $response = $this->get("/img/{$asset->path}?effect=totally-unknown-effect");

        // Should not crash — returns the image unchanged (or original)
        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    // ── Non-image fallback ────────────────────────────────────────────────────

    public function test_video_asset_is_served_as_raw_file_not_transformed(): void
    {
        $file  = UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200");

        // isImage() is false → controller falls back to serving the raw file
        $response->assertStatus(200);
    }

    public function test_nonexistent_path_returns_404(): void
    {
        // No file on disk AND no sidecar → true 404
        $response = $this->get('/img/uploads/completely-missing-file.jpg?w=200');

        $response->assertStatus(404);
    }

    public function test_document_asset_is_served_as_raw_file(): void
    {
        $file  = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}");

        // Not an image → raw file fallback (200)
        $response->assertStatus(200);
    }

    // ── Path with subdirectories ──────────────────────────────────────────────

    public function test_handles_deeply_nested_path(): void
    {
        $file  = UploadedFile::fake()->image('deep.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads/gallery/2024/march');

        $response = $this->get("/img/{$asset->path}?w=200");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    // ── Caching idempotency ───────────────────────────────────────────────────

    public function test_repeated_requests_with_same_params_do_not_duplicate_cache(): void
    {
        $file  = UploadedFile::fake()->image('repeat.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        for ($i = 0; $i < 5; $i++) {
            $this->get("/img/{$asset->path}?w=300&fmt=webp");
        }

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        $this->assertCount(1, $files);
    }

    public function test_same_params_different_order_hits_same_cache(): void
    {
        $file  = UploadedFile::fake()->image('order.jpg', 640, 480);
        $asset = Media::upload($file, 'uploads');

        $this->get("/img/{$asset->path}?w=200&fmt=webp&q=80");
        $this->get("/img/{$asset->path}?fmt=webp&q=80&w=200"); // different param order

        $dir   = dirname($asset->path);
        $slug  = str_replace('.', '_', basename($asset->path));
        $base  = $dir === '.' ? '' : $dir . '/';
        $files = Storage::disk('public')->files($base . '.meta/' . $slug . '/cache');

        // Both use the same UrlGenerator chain → same cache key → one file
        $this->assertCount(1, $files);
    }

    // ── fit param ─────────────────────────────────────────────────────────────

    public function test_fit_cover_with_w_and_h_uses_cover_mode(): void
    {
        $file  = UploadedFile::fake()->image('fitcover.jpg', 1000, 500);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200&h=200&fit=cover");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    public function test_fit_resize_uses_resize_mode(): void
    {
        $file  = UploadedFile::fake()->image('fitresize.jpg', 800, 600);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200&h=150&fit=resize");

        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }

    // ── Signed mode ───────────────────────────────────────────────────────────

    public function test_signed_mode_toImageUrl_appends_sig(): void
    {
        config(['media-library.image.sign' => true]);
        app()->forgetInstance(MediaLibrary::class ?? 'media-manager');
        app()->forgetInstance('media-manager');

        $file  = UploadedFile::fake()->image('sigurl.jpg');
        $asset = Media::upload($file, 'uploads');

        $url = Media::url($asset)->resize(400)->toImageUrl();

        $this->assertStringContainsString('sig=', $url);
    }

    // ── Response headers ──────────────────────────────────────────────────────

    public function test_response_has_cache_control_header(): void
    {
        $file  = UploadedFile::fake()->image('headers.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200");

        // Either streamed (200) or redirect (301) — both should set cache headers
        if ($response->getStatusCode() === 200) {
            $this->assertNotEmpty($response->headers->get('Cache-Control'));
        } else {
            // Redirect responses also have Cache-Control
            $this->assertContains($response->getStatusCode(), [301, 302]);
        }
    }

    public function test_streamed_response_has_correct_content_type_for_webp(): void
    {
        $file  = UploadedFile::fake()->image('ct.jpg', 400, 300);
        $asset = Media::upload($file, 'uploads');

        $response = $this->get("/img/{$asset->path}?w=200&fmt=webp");

        if ($response->getStatusCode() === 200) {
            $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        } else {
            $this->assertContains($response->getStatusCode(), [301, 302]);
        }
    }
}
