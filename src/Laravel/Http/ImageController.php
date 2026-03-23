<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;
use Posterity\MediaLibrary\Laravel\MediaManager;
use Posterity\MediaLibrary\Support\UrlGenerator;
use Posterity\MediaLibrary\Support\UrlSigner;

class ImageController
{
    use ServesTransformedImage;

    public function __construct(
        private MediaManager   $manager,
        private PresetRegistry $presets,
        private StorageAdapter $storage,
        private StorageAdapter $cacheStorage,
        private ?UrlSigner     $signer,
    ) {}

    public function __invoke(Request $request, string $path): SymfonyResponse
    {
        if ($this->signer !== null) {
            $params         = $request->query();
            $params['path'] = $path;

            if (! $this->signer->verify($params)) {
                abort(403, 'Invalid or missing image transform signature.');
            }
        }

        $asset = $this->manager->get($path);

        if ($asset === null || ! $asset->isImage()) {
            if ($this->storage->exists($path)) {
                $data = $this->storage->get($path);
                return response($data, 200, [
                    'Content-Type'  => $this->storage->mimeType($path),
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            }

            abort(404);
        }

        $generator = $this->buildGenerator($request, $asset);
        $cachePath = $generator->processAndStore();
        $cacheUrl  = $this->cacheStorage->url($cachePath);

        return $this->respond($cachePath, $cacheUrl, $generator->getOutputMime());
    }

    private function buildGenerator(Request $request, Asset $asset): UrlGenerator
    {
        $gen = $this->manager->url($asset);

        $w      = $request->integer('w');
        $h      = $request->integer('h');
        $effect = (string) ($request->query('effect', ''));

        $fx = $request->has('fx') ? $request->integer('fx') : $asset->focusX;
        $fy = $request->has('fy') ? $request->integer('fy') : $asset->focusY;

        if ($request->has('fx') || $request->has('fy')) {
            $gen->focus($fx, $fy);
        }

        if ($effect !== '' && $this->presets->has($effect)) {
            $gen->preset($effect);

            if ($w > 0) {
                $gen->resize($w, $h > 0 ? $h : null);
            }
        } elseif ($effect !== '') {
            $this->applyEffectShorthand($gen, $effect, $w, $h);
        } elseif ($w > 0) {
            $fit = (string) ($request->query('fit', 'resize'));

            if ($fit === 'cover' && $h > 0) {
                $gen->cover($w, $h);
            } else {
                $gen->resize($w, $h > 0 ? $h : null);
            }
        }

        if ($blur = $request->integer('blur')) {
            $gen->blur(min($blur, 100));
        }

        if ($request->boolean('grey')) {
            $gen->greyscale();
        }

        if ($request->boolean('flip')) {
            $gen->flip();
        }

        if ($request->boolean('flop')) {
            $gen->flop();
        }

        $fmt = (string) $request->query('fmt', '');
        if ($fmt !== '') {
            if (! FormatEffect::isSupported($fmt)) {
                abort(422, "Unsupported format \"{$fmt}\".");
            }
            $gen->format($fmt, $request->integer('q', 80));
        } elseif ($q = $request->integer('q')) {
            if (FormatEffect::isSupported($asset->extension)) {
                $gen->format($asset->extension, $q);
            }
        }

        return $gen;
    }

    private function applyEffectShorthand(
        UrlGenerator $gen,
        string       $effect,
        int          $w,
        int          $h,
    ): void {
        if ($effect === 'cover' || $effect === 'crop') {
            if ($w > 0 && $h > 0) {
                $gen->cover($w, $h);
            }
        } elseif ($effect === 'resize' || $effect === 'scale' || $effect === 'fit') {
            if ($w > 0) {
                $gen->resize($w, $h > 0 ? $h : null);
            }
        } elseif ($effect === 'blur') {
            $gen->blur($w > 0 ? $w : 10);
        } elseif ($effect === 'greyscale' || $effect === 'grey' || $effect === 'bw') {
            $gen->greyscale();
        } elseif ($effect === 'flip') {
            $gen->flip();
        } elseif ($effect === 'flop') {
            $gen->flop();
        }
    }
}
