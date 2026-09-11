<?php

namespace App\Enums;

enum EngagementMetric: string
{
    case Likes = 'likes';
    case Comments = 'comments';
    case Views = 'views';
    case WatchHours = 'watch_hours';

    public function label(): string
    {
        return match ($this) {
            self::Likes => 'Likes',
            self::Comments => 'Comments',
            self::Views => 'Views',
            self::WatchHours => 'Watch hours',
        };
    }

    public function usesCountChangeVerification(): bool
    {
        return $this === self::Likes || $this === self::Comments;
    }

    public function usesWatchSession(): bool
    {
        // Timed watch UX for views/watch-hours except X (open-on-platform action).
        return $this === self::WatchHours || $this === self::Views;
    }

    public function requiresTimedSession(?string $platform = null): bool
    {
        if ($this === self::WatchHours) {
            return true;
        }

        if ($this !== self::Views) {
            return false;
        }

        // X views: open-on-platform action — no fake view countdown.
        if (in_array($platform, ['x', 'twitter'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve metric from product slug (e.g. youtube-likes → likes).
     */
    public static function fromProductSlug(?string $slug): ?self
    {
        if (! filled($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));

        return match (true) {
            str_ends_with($slug, '-likes') => self::Likes,
            str_ends_with($slug, '-comments') => self::Comments,
            str_ends_with($slug, '-watch-hours') => self::WatchHours,
            str_ends_with($slug, '-views') => self::Views,
            default => null,
        };
    }

    public static function platformFromProductSlug(?string $slug): ?string
    {
        if (! filled($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));
        foreach (['youtube', 'facebook', 'instagram', 'tiktok', 'twitter'] as $platform) {
            if (str_starts_with($slug, $platform.'-')) {
                return $platform === 'twitter' ? 'x' : $platform;
            }
        }

        return null;
    }
}
