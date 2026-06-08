<?php

declare(strict_types=1);

use App\Support\Routing\LocalizedRoutePaths;

if (! function_exists('route_path')) {
    function route_path(string $key): string
    {
        return LocalizedRoutePaths::get($key);
    }
}
