<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Support;

use RuntimeException;
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\Contracts\Processor;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use InvalidArgumentException;
use Posterity\MediaLibrary\Core\Effects\BlurEffect;
use Posterity\MediaLibrary\Core\Effects\CoverEffect;
use Posterity\MediaLibrary\Core\Effects\FlipEffect;
use Posterity\MediaLibrary\Core\Effects\FlopEffect;
use Posterity\MediaLibrary\Core\Effects\FocusEffect;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Core\Effects\GreyscaleEffect;
use Posterity\MediaLibrary\Core\Effects\ResizeEffect;
use Posterity\MediaLibrary\Core\Effects\WatermarkEffect;
use Posterity\MediaLibrary\Core\Pipeline\EffectPipeline;
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;

class UrlGenerator
{
    private EffectPipeline $pipeline;
    private int $focusX;
    private int $focusY;
    private array $urlParams = [];

    public function __construct(
        private Asset          $asset,
        private Processor      $processor,
        private PresetRegistry $presets,
        private StorageAdapter $storage,
        private StorageAdapter $cacheStorage,
        private string         $metaFolder = '.meta',
        private ?UrlSigner     $signer = null,
        private string         $transformBase = '',
        private string         $imageBase = '',
        private ?UrlSigner     $imageSigner = null,
    ) {
        $this->pipeline = new EffectPipeline();
        $this->focusX   = $asset->focusX;
        $this->focusY   = $asset->focusY;
    }

    public function preset(string $name): self
    {
        foreach ($this->presets->get($name) as $effect) {
            if ($effect instanceof WatermarkEffect && ! $effect->isResolved()) {
                if ($effect->getImagePath() === '') {
                    continue;
                }
                $data = $this->storage->get($effect->getImagePath());
                if ($data === null) {
                    continue;
                }
                $effect = $effect->withImageData($data);
            }
            $this->pipeline->pipe($effect);
        }

        $this->urlParams['effect'] = $name;

        return $this;
    }

    public function resize(int $width, ?int $height = null): self
    {
        $this->pipeline->pipe(new ResizeEffect($width, $height));

        $this->urlParams['w']   = $width;
        $this->urlParams['fit'] = 'resize';
        if ($height !== null) {
            $this->urlParams['h'] = $height;
        }

        return $this;
    }

    public function cover(int $width, int $height): self
    {
        $this->pipeline->pipe(new CoverEffect($width, $height, $this->focusX, $this->focusY));

        $this->urlParams['w']   = $width;
        $this->urlParams['h']   = $height;
        $this->urlParams['fit'] = 'cover';
        $this->urlParams['fx']  = $this->focusX;
        $this->urlParams['fy']  = $this->focusY;

        return $this;
    }

    public function focus(int $x, int $y): self
    {
        $this->focusX = $x;
        $this->focusY = $y;
        $this->pipeline->pipe(new FocusEffect($x, $y));

        $this->urlParams['fx'] = $x;
        $this->urlParams['fy'] = $y;

        return $this;
    }

    public function blur(int $amount = 10): self
    {
        $this->pipeline->pipe(new BlurEffect($amount));
        $this->urlParams['blur'] = $amount;

        return $this;
    }

    public function greyscale(): self
    {
        $this->pipeline->pipe(new GreyscaleEffect());
        $this->urlParams['grey'] = 1;

        return $this;
    }

    public function flip(): self
    {
        $this->pipeline->pipe(new FlipEffect());
        $this->urlParams['flip'] = 1;

        return $this;
    }

    public function flop(): self
    {
        $this->pipeline->pipe(new FlopEffect());
        $this->urlParams['flop'] = 1;

        return $this;
    }

    public function format(string $type, int $quality = 80): self
    {
        if (! FormatEffect::isSupported($type)) {
            throw new InvalidArgumentException(
                "Unsupported image format \"{$type}\". Supported: jpg, jpeg, webp, avif, png, gif"
            );
        }

        $this->pipeline->pipe(new FormatEffect($type, $quality));

        $this->urlParams['fmt'] = $type;
        $this->urlParams['q']   = $quality;

        return $this;
    }

    public function watermark(string $imagePath, string $position = 'bottom-right', int $opacity = 80): self
    {
        $data = $this->storage->get($imagePath);

        if ($data !== null) {
            $this->pipeline->pipe(new WatermarkEffect($data, $position, $opacity));
        }

        return $this;
    }

    public function toUrl(): string
    {
        if (! $this->asset->isImage() || $this->pipeline->isEmpty()) {
            return $this->storage->url($this->asset->path);
        }

        $cachePath = $this->processAndStore();

        return $this->cacheStorage->url($cachePath);
    }

    public function processAndStore(): string
    {
        $formatEffect = $this->pipeline->getFormatEffect();
        $ext          = $formatEffect?->getType() ?? $this->asset->extension;
        $cachePath    = $this->buildCachePath($ext);

        if ($this->cacheStorage->exists($cachePath)) {
            return $cachePath;
        }

        $original = $this->storage->get($this->asset->path);

        if ($original === null) {
            throw new RuntimeException("Original file not found: {$this->asset->path}");
        }

        $image   = $this->processor->read($original);
        $encoded = $this->processor->process($image, $this->pipeline);

        $this->cacheStorage->put($cachePath, $encoded);

        return $cachePath;
    }

    public function getCachePath(): string
    {
        $formatEffect = $this->pipeline->getFormatEffect();
        $ext          = $formatEffect?->getType() ?? $this->asset->extension;

        return $this->buildCachePath($ext);
    }

    public function getOutputMime(): string
    {
        $formatEffect = $this->pipeline->getFormatEffect();
        $type         = $formatEffect?->getType() ?? $this->asset->extension;

        return match ($type) {
            'webp'        => 'image/webp',
            'avif'        => 'image/avif',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default       => 'image/jpeg',
        };
    }

    public function toSignedUrl(): string
    {
        if ($this->signer === null) {
            throw new RuntimeException(
                'HTTP transform is disabled. Set media-library.transform.enabled = true.'
            );
        }

        $params         = array_filter($this->urlParams, fn($v) => $v !== null && $v !== '');
        $params['uuid'] = $this->asset->uuid;
        $params['sig']  = $this->signer->sign($params);

        unset($params['uuid']);

        return rtrim($this->transformBase, '/') . '/' . $this->asset->uuid . '?' . http_build_query($params);
    }

    public function toImageUrl(): string
    {
        if ($this->imageBase === '') {
            throw new RuntimeException(
                'Image transform endpoint is disabled. Set media-library.image.enabled = true.'
            );
        }

        $params = array_filter($this->urlParams, fn($v) => $v !== null && $v !== '');

        if ($this->imageSigner !== null) {
            $sigParams         = $params;
            $sigParams['path'] = $this->asset->path;
            $params['sig']     = $this->imageSigner->sign($sigParams);
        }

        $base = rtrim($this->imageBase, '/') . '/' . ltrim($this->asset->path, '/');

        return $base . (empty($params) ? '' : '?' . http_build_query($params));
    }

    public function srcset(array $widths): string
    {
        return implode(', ', array_map(
            fn(int $w) => $this->cloneForWidth($w)->toUrl() . " {$w}w",
            $widths,
        ));
    }

    public function imageSrcset(array $widths): string
    {
        return implode(', ', array_map(
            fn(int $w) => $this->cloneForWidth($w)->toImageUrl() . " {$w}w",
            $widths,
        ));
    }

    public function signedSrcset(array $widths): string
    {
        return implode(', ', array_map(
            fn(int $w) => $this->cloneForWidth($w)->toSignedUrl() . " {$w}w",
            $widths,
        ));
    }

    public function __toString(): string
    {
        return $this->toUrl();
    }

    private function cloneForWidth(int $width): self
    {
        $originalFit = $this->urlParams['fit'] ?? 'resize';
        $originalW   = (int) ($this->urlParams['w'] ?? 0);
        $originalH   = (int) ($this->urlParams['h'] ?? 0);

        $clone            = clone $this;
        $clone->pipeline  = new EffectPipeline();
        $clone->urlParams = [];

        if ($originalFit === 'cover' && $originalW > 0 && $originalH > 0) {
            $h = (int) round($originalH * $width / $originalW);
            $clone->cover($width, max(1, $h));
        } else {
            $clone->resize($width);
        }

        foreach ($this->pipeline->getEffects() as $effect) {
            if ($effect instanceof ResizeEffect || $effect instanceof CoverEffect) {
                continue;
            }
            $clone->pipeline->pipe($effect);
        }

        foreach ($this->urlParams as $key => $value) {
            if (in_array($key, ['w', 'h', 'fit', 'fx', 'fy'])) {
                continue;
            }
            $clone->urlParams[$key] = $value;
        }

        return $clone;
    }

    private function buildCachePath(string $ext): string
    {
        $dir      = dirname($this->asset->path);
        $slug     = str_replace('.', '_', basename($this->asset->path));
        $cacheKey = $this->pipeline->getCacheKey();
        $base     = $dir === '.' ? '' : $dir . '/';

        return "{$base}{$this->metaFolder}/{$slug}/cache/{$cacheKey}.{$ext}";
    }
}
