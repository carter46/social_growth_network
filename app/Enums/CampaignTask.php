<?php

namespace App\Enums;

enum CampaignTask: string
{
    case Subscribe = 'subscribe';
    case Watch = 'watch';
    case Comment = 'comment';
    case Like = 'like';

    public function label(): string
    {
        return match ($this) {
            self::Subscribe => 'Subscribe to the channel',
            self::Watch => 'Watch the video',
            self::Comment => 'Comment on the video',
            self::Like => 'Like the video',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Subscribe => 'Subscribe',
            self::Watch => 'Watch',
            self::Comment => 'Comment',
            self::Like => 'Like',
        };
    }

    /** YouTube only counts a subscribe or like once per account. */
    public function isRepeatable(): bool
    {
        return $this === self::Watch || $this === self::Comment;
    }

    public static function fromMetric(?EngagementMetric $metric): ?self
    {
        return match ($metric) {
            EngagementMetric::Subscribers => self::Subscribe,
            EngagementMetric::WatchHours, EngagementMetric::Views => self::Watch,
            EngagementMetric::Comments => self::Comment,
            EngagementMetric::Likes => self::Like,
            default => null,
        };
    }
}
