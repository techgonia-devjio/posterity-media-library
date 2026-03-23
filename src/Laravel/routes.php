<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Posterity\MediaLibrary\Laravel\Http\ImageController;
use Posterity\MediaLibrary\Laravel\Http\TransformController;

if (config('media-library.transform.enabled')) {
    Route::get(
        config('media-library.transform.route_prefix', 'media/img') . '/{uuid}',
        TransformController::class,
    )
    ->middleware(config('media-library.transform.middleware', []))
    ->name('posterity.transform');
}

if (config('media-library.image.enabled')) {
    Route::get(
        config('media-library.image.prefix', 'img') . '/{path}',
        ImageController::class,
    )
    ->where('path', '.*')
    ->middleware(config('media-library.image.middleware', ['throttle:300,1']))
    ->name('posterity.image');
}
