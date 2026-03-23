<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Posterity\MediaLibrary\Laravel\LaravelStorageAdapter;
use Posterity\MediaLibrary\Laravel\MediaManager;

class SyncMediaCommand extends Command
{
    protected $signature   = 'posterity:media:sync {disk=public : The disk to scan}';
    protected $description = 'Scan a media disk and rebuild the UUID → path cache';

    public function handle(MediaManager $manager): int
    {
        $diskName = (string) $this->argument('disk');
        $this->info("Scanning disk: {$diskName} …");

        $defaultDisk = config('media-library.disk', 'public');
        $storage = $diskName === $defaultDisk
            ? app('posterity.storage')
            : new LaravelStorageAdapter(Storage::disk($diskName));

        $metaFolder = config('media-library.meta_folder', '.meta');
        $all        = $storage->allFiles();
        $metas      = array_filter(
            $all,
            fn(string $f) => str_contains($f, "/{$metaFolder}/") && str_ends_with($f, 'metadata.json'),
        );

        $count = 0;

        foreach ($metas as $metaFile) {
            $json = $storage->get($metaFile);

            if ($json === null) {
                continue;
            }

            $data = json_decode($json, true);

            if (! is_array($data) || empty($data['path'])) {
                continue;
            }

            $asset = $manager->get($data['path'], $diskName);

            if ($asset !== null) {
                $manager->save($asset);
                $count++;
            }
        }

        $this->info("Synced {$count} asset(s) on disk '{$diskName}'.");

        return self::SUCCESS;
    }
}
