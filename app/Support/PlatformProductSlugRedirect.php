<?php

namespace App\Support;

/**
 * Canonicalize retired catalog product slugs (Views renames).
 */
final class PlatformProductSlugRedirect
{
    public static function resolve(string $slug): ?string
    {
        $map = config('platform_products.slug_redirects', []);

        if (! is_array($map) || ! isset($map[$slug])) {
            return null;
        }

        return (string) $map[$slug];
    }

    public static function canonical(string $slug): string
    {
        return self::resolve($slug) ?? $slug;
    }

    /**
     * Old slugs that must remain trim-safe until the seeder renames them.
     *
     * @return list<string>
     */
    public static function legacySlugs(): array
    {
        $map = config('platform_products.slug_redirects', []);

        if (! is_array($map)) {
            return [];
        }

        return array_values(array_filter(array_keys($map), fn ($key) => is_string($key) && $key !== ''));
    }
}
