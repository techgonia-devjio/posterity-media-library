<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait ServesTransformedImage
{
    private function respond(string $cachePath, string $cacheUrl, string $mime): SymfonyResponse
    {
        $maxAge      = (int) config('media-library.delivery.cache_max_age', 31536000);
        $cacheHeader = "public, max-age={$maxAge}, immutable";

        if (str_starts_with($cacheUrl, 'http://') || str_starts_with($cacheUrl, 'https://')) {
            return redirect($cacheUrl, 301)
                ->header('Cache-Control', $cacheHeader);
        }

        $data = $this->cacheStorage->get($cachePath);

        if ($data === null) {
            abort(500, 'Failed to read cached image.');
        }

        return response($data, 200, [
            'Content-Type'   => $mime,
            'Content-Length' => strlen($data),
            'Cache-Control'  => $cacheHeader,
            'ETag'           => '"' . md5($cachePath) . '"',
            'Vary'           => 'Accept',
        ]);
    }
}
