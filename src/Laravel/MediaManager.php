<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Posterity\MediaLibrary\Core\Asset;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use Posterity\MediaLibrary\Core\MediaLibrary;
use Posterity\MediaLibrary\Jobs\ProcessAssetJob;
use Posterity\MediaLibrary\Support\UrlGenerator;

class MediaManager
{
    public function __construct(private MediaLibrary $library) {}

    public function upload(
        UploadedFile $file,
        string       $path = 'uploads',
        string       $disk = '',
        array        $metadata = [],
    ): Asset {
        $disk             = $disk !== '' ? $disk : config('media-library.disk', 'public');
        $originalFilename = $file->getClientOriginalName();
        $extension        = $file->getClientOriginalExtension();
        $filename         = (string) Str::uuid() . '.' . $extension;

        $asset = $this->library->store(
            contents:         $file->get(),
            path:             $path,
            disk:             $disk,
            filename:         $filename,
            originalFilename: $originalFilename,
            extraMetadata:    $metadata,
            storage:          $this->adapterForDisk($disk),
        );

        $this->dispatchPresets($asset);

        return $asset;
    }

    public function url(Asset $asset): UrlGenerator
    {
        return $this->library->url($asset, $this->adapterForDisk($asset->disk));
    }

    public function get(string $path, string $disk = ''): ?Asset
    {
        $disk = $disk !== '' ? $disk : config('media-library.disk', 'public');

        return $this->library->get($path, $this->adapterForDisk($disk));
    }

    public function findByUuid(string $uuid, string $disk = ''): ?Asset
    {
        if ($disk === '') {
            $disk = $this->library->diskForUuid($uuid)
                ?? config('media-library.disk', 'public');
        }

        return $this->library->findByUuid($uuid, $this->adapterForDisk($disk));
    }

    public function save(Asset $asset): void
    {
        $this->library->save($asset, $this->adapterForDisk($asset->disk));
    }

    public function delete(string $path, string $disk = ''): bool
    {
        $disk = $disk !== '' ? $disk : config('media-library.disk', 'public');

        return $this->library->delete($path, $this->adapterForDisk($disk));
    }

    private function adapterForDisk(string $disk): StorageAdapter
    {
        $default = config('media-library.disk', 'public');

        if ($disk === $default) {
            return app('posterity.storage');
        }

        return new LaravelStorageAdapter(Storage::disk($disk));
    }

    private function dispatchPresets(Asset $asset): void
    {
        if (! config('media-library.queue.enabled', false)) {
            return;
        }

        $presets = config('media-library.queue.precompute_presets', []);

        if (empty($presets) || ! $asset->isImage()) {
            return;
        }

        ProcessAssetJob::dispatch($asset, $presets)
            ->onQueue(config('media-library.queue.name', 'default'));
    }
}
