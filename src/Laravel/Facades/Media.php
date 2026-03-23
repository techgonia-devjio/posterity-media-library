<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Media extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'media-manager';
    }
}
