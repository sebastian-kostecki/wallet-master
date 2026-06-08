<?php

declare(strict_types=1);

namespace App\Support\Routing;

final class LocalizedRoutePaths
{
    public static function get(string $key, ?string $locale = null): string
    {
        $locale ??= (string) config('routes.default_locale', 'pl');

        /** @var array<string, string>|null $segments */
        $segments = config("routes.segments.{$locale}");

        if (is_array($segments)) {
            $segment = $segments[$key] ?? null;

            if (is_string($segment)) {
                return $segment;
            }
        }

        return $key;
    }

    public static function legacy(string $key): string
    {
        return self::get($key, 'en');
    }
}
