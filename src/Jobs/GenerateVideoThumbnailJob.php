<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Laravel\MediaManager;

class GenerateVideoThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Asset $asset,
    ) {}

    public function handle(MediaManager $manager): void
    {
        if (! $this->asset->isVideo()) {
            return;
        }

        $ffmpegPath    = (string) config('media-library.video_thumbnail.ffmpeg_path', '/usr/bin/ffmpeg');
        $offsetSeconds = (int) config('media-library.video_thumbnail.offset_seconds', 1);
        $metaFolder    = (string) config('media-library.meta_folder', '.meta');

        if (! file_exists($ffmpegPath) || ! is_executable($ffmpegPath)) {
            return;
        }

        $storage   = app('posterity.storage');
        $localPath = $storage->path($this->asset->path);

        $dir           = dirname($this->asset->path);
        $slug          = str_replace('.', '_', basename($this->asset->path));
        $base          = $dir === '.' ? '' : $dir . '/';
        $thumbnailPath = "{$base}{$metaFolder}/{$slug}/thumbnail.jpg";

        $tmpFile = tempnam(sys_get_temp_dir(), 'posterity_thumb_') . '.jpg';

        try {
            $cmd = sprintf(
                '%s -y -i %s -ss %d -vframes 1 %s 2>/dev/null',
                escapeshellarg($ffmpegPath),
                escapeshellarg($localPath),
                $offsetSeconds,
                escapeshellarg($tmpFile),
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || ! file_exists($tmpFile)) {
                return;
            }

            $data = file_get_contents($tmpFile);

            if ($data === false || $data === '') {
                return;
            }

            $storage->put($thumbnailPath, $data);

            $updated            = clone $this->asset;
            $updated->thumbnail = $thumbnailPath;

            $manager->save($updated);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
