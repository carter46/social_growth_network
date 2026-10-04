<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUGS = [
        'youtube-views', 'youtube-likes', 'youtube-comments', 'youtube-watch-hours',
        'facebook-views', 'facebook-likes', 'facebook-comments',
        'instagram-views', 'instagram-likes', 'instagram-comments',
        'tiktok-views', 'tiktok-likes', 'tiktok-comments',
        'twitter-views', 'twitter-likes', 'twitter-comments',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('platform_products')) {
            return;
        }

        $hasShort = Schema::hasColumn('platform_products', 'short_description');
        $columns = $hasShort ? ['id', 'slug', 'title', 'description', 'short_description'] : ['id', 'slug', 'title', 'description'];

        $rows = DB::table('platform_products')->whereIn('slug', self::SLUGS)->get($columns);

        foreach ($rows as $row) {
            $title = (string) $row->title;
            $description = $this->descriptionFor((string) $row->slug);
            $updates = [];

            if ($row->description === "Get started quickly with {$title}. Includes setup guidance, support, and clear deliverables.") {
                $updates['description'] = $description;
            }

            if ($hasShort && $row->short_description === "Ready-to-use {$title} for social growth.") {
                $updates['short_description'] = strtok($description, '.').'.';
            }

            if ($updates !== []) {
                DB::table('platform_products')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Admin-visible copy is not reverted to the old placeholder.
    }

    private function descriptionFor(string $slug): string
    {
        [$platform, $metric] = explode('-', $slug, 2);

        if ($metric === 'watch-hours') {
            return 'Add watch time to a public YouTube video. Choose a package, add your video link at checkout, and track your campaign from your account.';
        }

        $name = match ($platform) {
            'youtube' => 'YouTube',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            default => '',
        };

        $target = match (true) {
            $platform === 'twitter' => 'post on X (Twitter)',
            $platform === 'instagram' => 'Instagram Reel or post',
            $platform === 'tiktok' => 'TikTok video',
            $metric === 'views' => $name.' video',
            default => $name.' post or video',
        };

        if ($metric === 'views') {
            $link = in_array($platform, ['youtube', 'facebook', 'tiktok'], true) ? 'your video link' : 'the link';

            return "Order views for a public {$target}. Choose a package, add {$link} at checkout, and track your campaign from your account.";
        }

        return "Order {$metric} for a public {$target}. Choose a package, add the link at checkout, and track progress from your account.";
    }
};
