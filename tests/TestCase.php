<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests;

use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;
use Posterity\MediaLibrary\Laravel\MediaServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MediaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('media-library.disk', 'public');
        $app['config']->set('media-library.meta_folder', '.meta');
        $app['config']->set('media-library.cache.enabled', false); // NullCacheAdapter in tests
        $app['config']->set('media-library.queue.enabled', false);
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => 'http://localhost/storage',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }
}
