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

/**
 * Background job: pre-compute named presets for a freshly uploaded image.
 *
 * Dispatched by MediaManager::upload() when:
 *   - media-library.queue.enabled  = true
 *   - media-library.queue.precompute_presets is non-empty
 *   - the uploaded asset is an image
 */
class ProcessAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param Asset    $asset   The freshly stored asset to process.
     * @param string[] $presets Preset names to generate (e.g. ['thumb', 'hero']).
     */
    public function __construct(
        public readonly Asset $asset,
        public readonly array $presets = [],
    ) {}

    public function handle(MediaManager $manager): void
    {
        foreach ($this->presets as $preset) {
            // toUrl() processes and caches the variant; subsequent requests hit the cache.
            $manager->url($this->asset)->preset($preset)->toUrl();
        }
    }
}
