<?php

namespace App\Enums;

enum EngagementMetric: string
{
    case Likes = 'likes';
    case Comments = 'comments';
    case Views = 'views';
    case WatchHours = 'watch_hours';
    case Subscribers = 'subscribers';

    public function label(): string
    {
        return match ($this) {
            self::Likes => 'Likes',
            self::Comments => 'Comments',
            self::Views => 'Views',
            self::WatchHours => 'Watch hours',
            self::Subscribers => 'Subscribers',
        };
    }

    public function usesCountChangeVerification(): bool
    {
        return $this === self::Likes || $this === self::Comments;
    }

    public function usesWatchSession(): bool
    {
        return $this === self::WatchHours || $this === self::Views;
    }

    /** Subscribers targets a channel, not a video. */
    public function targetsChannel(): bool
    {
        return $this === self::Subscribers;
    }

    public function requiresTimedSession(?string $platform = null): bool
    {
        return $this === self::WatchHours || $this === self::Views;
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
            str_ends_with($slug, '-subscribers') => self::Subscribers,
            default => null,
        };
    }

    public static function platformFromProductSlug(?string $slug): ?string
    {
        if (! filled($slug)) {
            return null;
        }

        return str_starts_with(strtolower(trim($slug)), 'youtube-') ? 'youtube' : null;
    }
}
