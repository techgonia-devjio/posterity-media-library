<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use Posterity\MediaLibrary\Core\Effects\FormatEffect;
use Posterity\MediaLibrary\Laravel\MediaManager;
use Posterity\MediaLibrary\Support\UrlGenerator;
use Posterity\MediaLibrary\Support\UrlSigner;

class TransformController
{
    use ServesTransformedImage;

    public function __construct(
        private MediaManager   $manager,
        private UrlSigner      $signer,
        private StorageAdapter $cacheStorage,
    ) {}

    public function __invoke(Request $request, string $uuid): SymfonyResponse
    {
        $allParams         = $request->query();
        $allParams['uuid'] = $uuid;

        if (! $this->signer->verify($allParams)) {
            abort(403, 'Invalid or missing transform signature.');
        }

        $asset = $this->manager->findByUuid($uuid);

        if ($asset === null || ! $asset->isImage()) {
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

        $preset = $request->query('effect') ?? $request->query('preset');
        if ($preset) {
            $gen->preset((string) $preset);
        }

        $fx = $request->integer('fx', $asset->focusX);
        $fy = $request->integer('fy', $asset->focusY);

        if ($fx !== $asset->focusX || $fy !== $asset->focusY) {
            $gen->focus($fx, $fy);
        }

        $w   = $request->integer('w');
        $h   = $request->integer('h');
        $fit = $request->query('fit', 'resize');

        if ($w > 0) {
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

        $fmt = (string) ($request->query('fmt') ?? '');
        if ($fmt !== '') {
            if (! FormatEffect::isSupported($fmt)) {
                abort(422, "Unsupported format \"{$fmt}\".");
            }
            $gen->format($fmt, $request->integer('q', 80));
        }

        return $gen;
    }
}
