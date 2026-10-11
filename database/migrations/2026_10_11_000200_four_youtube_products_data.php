<?php

use App\Models\PlatformProductVariant;
use App\Services\Engagement\TargetUrlValidator;
use Database\Seeders\PlatformCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data only: retitles the combined products, adds the draft
 * "YouTube Subscribers + Watch Hours + Comments" product, fills campaigns.video_id /
 * channel_id from existing links, and records past paid likes and subscribes.
 */
return new class extends Migration
{
    private const NEW_SLUG = 'youtube-subscribers-watch-comments';

    private const NEW_TITLE = 'YouTube Subscribers + Watch Hours + Comments';

    private const BASE_PRICE = 12000;

    private const RETITLES = [
        'youtube-subscribers' => ['YouTube Subscribers', 'YouTube Subscribers + Watch Hours'],
        'youtube-comments' => ['YouTube Comments', 'YouTube Comments + Watch Hours'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('platform_products')) {
            $this->retitle();
            $this->ensureProductFour();
        }

        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'video_id')) {
            $this->backfillCampaignTargets();
        }

        if (Schema::hasTable('agent_youtube_actions') && Schema::hasTable('campaign_participations')) {
            $this->backfillActions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agent_youtube_actions')) {
            DB::table('agent_youtube_actions')->where('evidence', 'legacy_backfill')->delete();
        }
        // Product rows are kept so orders and campaigns never lose their product.
    }

    private function retitle(): void
    {
        foreach (self::RETITLES as $slug => [$old, $new]) {
            DB::table('platform_products')
                ->where('slug', $slug)
                ->where('title', $old)
                ->update(['title' => $new, 'description' => PlatformCatalogSeeder::descriptionFor($slug, $new)]);
        }
    }

    private function ensureProductFour(): void
    {
        $now = now();
        $productId = DB::table('platform_products')->where('slug', self::NEW_SLUG)->value('id');

        if (! $productId) {
            $row = [
                'slug' => self::NEW_SLUG,
                'title' => self::NEW_TITLE,
                'product_type' => 'social_service',
                'description' => PlatformCatalogSeeder::descriptionFor(self::NEW_SLUG, self::NEW_TITLE),
                'status' => 'draft',
                'sort_order' => (int) DB::table('platform_products')->max('sort_order') + 1,
                'base_price' => self::BASE_PRICE,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ([
                'is_featured' => false,
                'currency' => 'NGN',
                'provider' => 'manual',
                'fulfillment_mode' => 'manual',
                'auto_renew' => false,
            ] as $column => $value) {
                if (Schema::hasColumn('platform_products', $column)) {
                    $row[$column] = $value;
                }
            }

            $productId = DB::table('platform_products')->insertGetId($row);
        }

        $this->attachToYoutube((int) $productId);
        $this->ensureStandardVariant((int) $productId, $now);
    }

    private function attachToYoutube(int $productId): void
    {
        $product = DB::table('platform_products')->where('id', $productId)->first();
        $sibling = DB::table('platform_products')->where('slug', 'youtube-views')->first();
        $updates = [];

        if (Schema::hasColumn('platform_products', 'service_category_id') && empty($product->service_category_id)) {
            $categoryId = Schema::hasTable('service_categories')
                ? DB::table('service_categories')->where('slug', 'youtube')->value('id')
                : null;
            $categoryId ??= $sibling->service_category_id ?? null;
            if ($categoryId) {
                $updates['service_category_id'] = $categoryId;
            }
        }

        if (Schema::hasColumn('platform_products', 'product_type_id')
            && empty($product->product_type_id)
            && ! empty($sibling?->product_type_id)) {
            $updates['product_type_id'] = $sibling->product_type_id;
        }

        if ($updates !== []) {
            DB::table('platform_products')->where('id', $productId)->update($updates);
        }
    }

    private function ensureStandardVariant(int $productId, $now): void
    {
        if (! Schema::hasTable('platform_product_variants')
            || DB::table('platform_product_variants')->where('platform_product_id', $productId)->exists()) {
            return;
        }

        $units = PlatformProductVariant::REFERENCE_UNITS;
        $row = [
            'platform_product_id' => $productId,
            'name' => 'Standard',
            'price' => self::BASE_PRICE,
            'duration_months' => 1,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('platform_product_variants', 'pricing_mode')) {
            $row['pricing_mode'] = PlatformProductVariant::PRICING_PER_UNIT;
            $row['unit_price'] = PlatformProductVariant::computeUnitPriceFromPackagePrice(self::BASE_PRICE, $units);
            $row['min_units'] = $units;
            $row['max_units'] = PlatformProductVariant::DEFAULT_MAX_UNITS;
        }
        if (Schema::hasColumn('platform_product_variants', 'pricing_units')) {
            $row['pricing_units'] = $units;
        }

        DB::table('platform_product_variants')->insert($row);
    }

    /** Fills only empty columns; a channel ID is filled only from /channel/UC... links (no API calls here). */
    private function backfillCampaignTargets(): void
    {
        DB::table('campaigns')
            ->whereNotNull('target_url')
            ->where(fn ($q) => $q->whereNull('video_id')->orWhereNull('channel_id'))
            ->orderBy('id')
            ->select(['id', 'target_url', 'video_id', 'channel_id'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $updates = [];
                    $videoId = TargetUrlValidator::extractYoutubeVideoId($row->target_url);
                    if ($row->video_id === null && $videoId) {
                        $updates['video_id'] = substr($videoId, 0, 32);
                    }
                    $ref = TargetUrlValidator::extractYoutubeChannelRef($row->target_url);
                    if ($row->channel_id === null && ($ref['type'] ?? null) === 'id') {
                        $updates['channel_id'] = $ref['value'];
                    }
                    if ($updates !== []) {
                        DB::table('campaigns')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    private function backfillActions(): void
    {
        $now = now();

        DB::table('campaign_participations as p')
            ->join('campaigns as c', 'c.id', '=', 'p.campaign_id')
            ->where('p.status', 'paid')
            ->whereIn('c.engagement_metric', ['likes', 'subscribers'])
            ->select(['p.id', 'p.agent_id', 'p.campaign_id', 'p.paid_at', 'c.engagement_metric', 'c.video_id', 'c.channel_id'])
            ->orderBy('p.id')
            ->chunk(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $isLike = $row->engagement_metric === 'likes';
                    if ($isLike && ! $row->video_id) {
                        continue;
                    }

                    $exists = DB::table('agent_youtube_actions')
                        ->where('campaign_participation_id', $row->id)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    DB::table('agent_youtube_actions')->insert([
                        'user_id' => $row->agent_id,
                        'action' => $isLike ? 'like' : 'subscribe',
                        'target_video_id' => $isLike ? $row->video_id : null,
                        'target_channel_id' => $isLike ? null : $row->channel_id,
                        'campaign_id' => $row->campaign_id,
                        'campaign_participation_id' => $row->id,
                        'evidence' => 'legacy_backfill',
                        'status' => 'verified',
                        'verified_at' => $row->paid_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
