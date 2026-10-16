<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace old seeded YouTube product descriptions with creator-promotion copy.
 * A description an admin has edited is left alone.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const REPLACEMENTS = [
        'Each unit is one new agent who subscribes to your channel and watches your video. Add your video and channel links at checkout, and track your campaign from your account.' => 'Promote your channel and video to grow your subscriber base and watch time. Add your video and channel links at checkout, and track your campaign from your account.',
        'Each unit is one new agent who watches your video and leaves a comment that follows your instructions. Add your video link and comment instructions at checkout, and track your campaign from your account.' => 'Promote your video to increase watch time and conversation in the comments, guided by your instructions. Add your video link and comment instructions at checkout, and track your campaign from your account.',
        'Each unit is one new agent who subscribes to your channel, watches your video and leaves a comment that follows your instructions. Add your video and channel links at checkout, and track your campaign from your account.' => 'Promote your channel and video to grow subscribers, watch time and comments, guided by your instructions. Add your video and channel links at checkout, and track your campaign from your account.',
        'Order views for a public YouTube video. Choose a package, add your video link at checkout, and track your campaign from your account.' => 'Promote a public YouTube video to reach more viewers. Choose a package, add your video link at checkout, and track your campaign from your account.',
        'Order likes for a public YouTube video. Choose a package, add the link at checkout, and track progress from your account.' => 'Boost engagement on a public YouTube video. Choose a package, add the link at checkout, and track progress from your account.',
        'Order likes for a public YouTube post or video. Choose a package, add the link at checkout, and track progress from your account.' => 'Boost engagement on a public YouTube video. Choose a package, add the link at checkout, and track progress from your account.',
        'Order comments for a public YouTube post or video. Choose a package, add the link at checkout, and track progress from your account.' => 'Promote your video to increase watch time and conversation in the comments, guided by your instructions. Add your video link and comment instructions at checkout, and track your campaign from your account.',
        'Order comments for a public YouTube video. Choose a package, add the link at checkout, and track progress from your account.' => 'Promote your video to increase watch time and conversation in the comments, guided by your instructions. Add your video link and comment instructions at checkout, and track your campaign from your account.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('platform_products')) {
            return;
        }

        foreach (self::REPLACEMENTS as $old => $new) {
            DB::table('platform_products')
                ->where('description', $old)
                ->update(['description' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        //
    }
};
