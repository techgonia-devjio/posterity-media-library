<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Posterity\MediaLibrary\Commands\SyncMediaCommand;
use Posterity\MediaLibrary\Core\Contracts\CacheAdapter;
use Posterity\MediaLibrary\Core\Contracts\MetadataDriver;
use Posterity\MediaLibrary\Core\Contracts\Processor;
use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use Posterity\MediaLibrary\Core\MediaLibrary;
use Posterity\MediaLibrary\Core\Pipeline\PresetRegistry;
use Posterity\MediaLibrary\Drivers\JsonSidecarDriver;
use Posterity\MediaLibrary\Drivers\NullCacheAdapter;
use Posterity\MediaLibrary\Laravel\Http\ImageController;
use Posterity\MediaLibrary\Laravel\Http\TransformController;
use Posterity\MediaLibrary\Processors\InterventionProcessor;
use Posterity\MediaLibrary\Support\MetadataExtractor;
use Posterity\MediaLibrary\Support\UrlSigner;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/media-library.php', 'media-library');

        $this->app->singleton(MetadataDriver::class, function () {
            return new JsonSidecarDriver(config('media-library.meta_folder', '.meta'));
        });

        $this->app->singleton(Processor::class, function () {
            return new InterventionProcessor(config('media-library.image_driver', 'gd'));
        });

        $this->app->singleton(CacheAdapter::class, function ($app) {
            if (! config('media-library.cache.enabled', true)) {
                return new NullCacheAdapter();
            }

            return new LaravelCacheAdapter($app['cache']->store());
        });

        $this->app->singleton(PresetRegistry::class, function () {
            $registry = new PresetRegistry();
            $yamlPath = __DIR__ . '/../../config/presets.yaml';

            if (file_exists($yamlPath)) {
                $registry->loadFromYaml($yamlPath);
            }

            $presets = config('media-library.presets', []);
            if (! empty($presets)) {
                $registry->loadFromArray($presets);
            }

            return $registry;
        });

        $this->app->singleton(MetadataExtractor::class, fn() => new MetadataExtractor());

        $this->app->singleton(UrlSigner::class, function () {
            $key = config('app.key', '');

            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            return new UrlSigner($key);
        });

        $this->app->singleton('posterity.storage', function () {
            return new LaravelStorageAdapter(
                Storage::disk(config('media-library.disk', 'public'))
            );
        });

        $this->app->singleton('posterity.cache_storage', function () {
            $disk = config('media-library.cache_disk') ?? config('media-library.disk', 'public');

            return new LaravelStorageAdapter(Storage::disk($disk));
        });

        $this->app->bind(TransformController::class, function ($app) {
            return new TransformController(
                manager:      $app->make('media-manager'),
                signer:       $app->make(UrlSigner::class),
                cacheStorage: $app->make('posterity.cache_storage'),
            );
        });

        $this->app->bind(ImageController::class, function ($app) {
            $sign = config('media-library.image.sign', false);

            return new ImageController(
                manager:      $app->make('media-manager'),
                presets:      $app->make(PresetRegistry::class),
                storage:      $app->make('posterity.storage'),
                cacheStorage: $app->make('posterity.cache_storage'),
                signer:       $sign ? $app->make(UrlSigner::class) : null,
            );
        });

        $this->app->singleton(MediaLibrary::class, function ($app) {
            $signer = $app->make(UrlSigner::class);

            $transformEnabled = config('media-library.transform.enabled', false);
            $transformBase    = $transformEnabled
                ? url(config('media-library.transform.route_prefix', 'media/img'))
                : '';

            $imageEnabled = config('media-library.image.enabled', false);
            $imageBase    = $imageEnabled
                ? url(config('media-library.image.prefix', 'img'))
                : '';

            $imageSign = config('media-library.image.sign', false);

            return new MediaLibrary(
                storage:       $app->make('posterity.storage'),
                metadata:      $app->make(MetadataDriver::class),
                processor:     $app->make(Processor::class),
                presets:       $app->make(PresetRegistry::class),
                cache:         $app->make(CacheAdapter::class),
                extractor:     $app->make(MetadataExtractor::class),
                metaFolder:    config('media-library.meta_folder', '.meta'),
                cacheStorage:  $app->make('posterity.cache_storage'),
                signer:        $transformEnabled ? $signer : null,
                transformBase: $transformBase,
                imageBase:     $imageBase,
                imageSigner:   ($imageEnabled && $imageSign) ? $signer : null,
            );
        });

        $this->app->singleton('media-manager', function ($app) {
            return new MediaManager($app->make(MediaLibrary::class));
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'posterity');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/media-library.php' => config_path('media-library.php'),
                __DIR__ . '/../../config/presets.yaml'      => config_path('media-presets.yaml'),
            ], 'posterity-media-config');

            $this->commands([SyncMediaCommand::class]);
        }
    }
}
