<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace old seeded heading, tagline and meta description with creator-promotion copy.
 * A value an admin has edited is left alone.
 */
return new class extends Migration
{
    private const HEADING = 'Boost your YouTube visibility';

    private const TAGLINE = 'YouTube promotion campaigns that help creators get seen by more viewers.';

    private const META = 'Social Growth Network helps creators boost YouTube visibility, reach and engagement with clear, fixed-price promotion campaigns. No bots, fake views or fake subscribers.';

    /** @var array<string, array{0: string, 1: list<string>}> */
    private const UPDATES = [
        'site_heading' => [self::HEADING, [
            'The Ultimate Digital Service Marketplace',
            'Grow your social presence',
        ]],
        'site_tagline' => [self::TAGLINE, [
            'Connecting markets, empowering traders.',
            'Creators launch digital campaigns. Agents complete verified tasks. Secure payment and payouts in one place.',
            'YouTube growth packages with clear prices and secure checkout.',
        ]],
        'site_meta_description' => [self::META, [
            'NGN wallet marketplace. Deposit, buy with escrow, sell digital products and services.',
            'Social Growth Network connects Creators and Agents for YouTube campaign packages, proof verification, USD wallet funding, and payouts.',
            'Social Growth Network connects Creators and Agents for campaign packages, proof verification, wallet funding, and secure payouts.',
            'Buy YouTube growth and engagement services. Secure checkout with wallet or card.',
        ]],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        foreach (self::UPDATES as $key => [$new, $oldValues]) {
            DB::table('system_settings')
                ->where('key', $key)
                ->whereIn('value', $oldValues)
                ->update(['value' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        //
    }
};
