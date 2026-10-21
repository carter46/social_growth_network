<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class DashboardNavigation
{
    /**
     * Return the visible, sorted navigation tree for a dashboard role.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(string $role, ?User $user = null): array
    {
        $entries = (array) config("menus.{$role}", []);

        $entries = array_values(array_filter($entries, fn (array $entry): bool => self::isVisible($entry, $user)));

        foreach ($entries as &$entry) {
            if (($entry['type'] ?? 'link') !== 'group') {
                continue;
            }

            $entry['children'] = array_values(array_filter(
                (array) ($entry['children'] ?? []),
                fn (array $child): bool => self::isVisible($child, $user),
            ));

            usort($entry['children'], fn (array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
        }
        unset($entry);

        $entries = array_values(array_filter(
            $entries,
            fn (array $entry): bool => ($entry['type'] ?? 'link') !== 'group' || count($entry['children'] ?? []) > 0,
        ));

        // A group with a single child renders as a plain link — no pointless submenu.
        $entries = array_map(function (array $entry): array {
            if (($entry['type'] ?? 'link') !== 'group' || count($entry['children']) !== 1) {
                return $entry;
            }

            $child = $entry['children'][0];

            return [
                'type' => 'link',
                'id' => $entry['id'] ?? null,
                'label' => $child['label'] ?? $entry['label'] ?? '',
                'icon' => $child['icon'] ?? $entry['icon'] ?? null,
                'route' => $child['route'] ?? null,
                'match' => $child['match'] ?? null,
                'badge' => $child['badge'] ?? ($entry['badge'] ?? null),
                'keywords' => array_values(array_unique(array_merge(
                    Arr::wrap($entry['keywords'] ?? []),
                    Arr::wrap($child['keywords'] ?? []),
                    Arr::wrap($entry['label'] ?? []),
                ))),
                'permission' => $child['permission'] ?? ($entry['permission'] ?? null),
                'sort' => $entry['sort'] ?? 0,
            ];
        }, $entries);

        usort($entries, fn (array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        if ($role === 'admin') {
            $entries = self::applyQueueBadges($entries, AdminQueueCounts::all());
        }

        return $entries;
    }

    /**
     * Flat permission-filtered destination list for sidebar search and future Ctrl+K.
     *
     * @return list<array{id: string, label: string, group: string|null, url: string, icon: string|null, keywords: list<string>}>
     */
    public static function searchIndex(string $role, ?User $user = null): array
    {
        $index = [];

        foreach (self::for($role, $user) as $entry) {
            if (($entry['type'] ?? 'link') === 'group') {
                $groupLabel = (string) ($entry['label'] ?? '');
                foreach ((array) ($entry['children'] ?? []) as $child) {
                    $destination = self::destinationFromItem($child, $groupLabel);
                    if ($destination !== null) {
                        $index[] = $destination;
                    }
                }

                continue;
            }

            $destination = self::destinationFromItem($entry, null);
            if ($destination !== null) {
                $index[] = $destination;
            }
        }

        return $index;
    }

    /**
     * Match only explicitly declared route patterns. Root routes remain exact.
     */
    public static function isActive(array $item, ?string $routeName = null): bool
    {
        $routeName ??= request()->route()?->getName();

        if (! is_string($routeName) || $routeName === '') {
            return false;
        }

        $patterns = Arr::wrap($item['match'] ?? $item['route'] ?? []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    public static function groupIsActive(array $group, ?string $routeName = null): bool
    {
        foreach ((array) ($group['children'] ?? []) as $child) {
            if (self::isActive($child, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Accordion opens at most one group: the active parent when present.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return list<string>
     */
    public static function initiallyOpenGroups(array $entries, ?string $routeName = null): array
    {
        foreach ($entries as $entry) {
            if (($entry['type'] ?? 'link') !== 'group') {
                continue;
            }

            if (self::groupIsActive($entry, $routeName)) {
                return [(string) $entry['id']];
            }
        }

        foreach ($entries as $entry) {
            if (($entry['type'] ?? 'link') === 'group' && ($entry['default_open'] ?? false)) {
                return [(string) $entry['id']];
            }
        }

        return [];
    }

    /**
     * @return array{id: string, label: string, group: string|null, url: string, icon: string|null, keywords: list<string>}|null
     */
    private static function destinationFromItem(array $item, ?string $groupLabel): ?array
    {
        $routeName = $item['route'] ?? null;
        $fallbackUrl = is_string($item['url'] ?? null) ? $item['url'] : null;
        $url = null;

        if (is_string($routeName) && $routeName !== '' && Route::has($routeName)) {
            $url = route($routeName);
        } elseif (is_string($fallbackUrl) && $fallbackUrl !== '') {
            $url = $fallbackUrl;
        }

        if ($url === null) {
            return null;
        }

        $label = (string) ($item['label'] ?? '');
        $keywords = array_values(array_filter(array_map(
            'strval',
            array_merge(
                Arr::wrap($item['keywords'] ?? []),
                [$label, $groupLabel ?? ''],
            ),
        )));

        return [
            'id' => (string) ($item['id'] ?? $routeName ?? $fallbackUrl),
            'label' => $label,
            'group' => $groupLabel,
            'url' => $url,
            'icon' => $item['icon'] ?? null,
            'keywords' => $keywords,
        ];
    }

    private static function isVisible(array $item, ?User $user): bool
    {
        $permission = $item['permission'] ?? null;

        return ! is_string($permission) || $permission === '' || ($user?->can($permission) ?? false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private static function applyQueueBadges(array $entries, array $counts): array
    {
        foreach ($entries as &$entry) {
            if (($entry['type'] ?? 'link') === 'group') {
                $sum = 0;
                foreach ($entry['children'] as &$child) {
                    $count = (int) ($counts[$child['route'] ?? ''] ?? 0);
                    if ($count > 0) {
                        $child['badge'] = self::formatCount($count);
                        $sum += $count;
                    }
                }
                unset($child);
                if ($sum > 0) {
                    $entry['badge'] = self::formatCount($sum);
                }

                continue;
            }

            $count = (int) ($counts[$entry['route'] ?? ''] ?? 0);
            if ($count > 0) {
                $entry['badge'] = self::formatCount($count);
            }
        }
        unset($entry);

        return $entries;
    }

    private static function formatCount(int $count): string
    {
        return $count > 99 ? '99+' : (string) $count;
    }
}
