<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'platform_fee_percent' => '2.5',
            'withdrawal_min_amount' => '100',
            'withdrawal_max_amount' => '1000000',
            'deposit_min_amount' => '100',
            'live_chat_provider' => 'none',
            'smartsupp_key' => '',
            'jivo_widget_id' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_email_alt' => '',
            'site_name' => config('app.name', 'Social Growth Network'),
            'site_short_name' => 'Social Growth',
            'site_heading' => 'Grow your social presence',
            'site_tagline' => 'Instagram, TikTok, YouTube, Twitter/X, and Facebook growth packs — clear deliverables, secure checkout.',
            'site_meta_description' => 'Buy social media growth and engagement services. Secure checkout with wallet or card.',
            'contact_timezone' => 'Africa/Lagos',
        ];

        foreach ($defaults as $key => $value) {
            if (SystemSetting::where('key', $key)->doesntExist()) {
                SystemSetting::set($key, $value);
            }
        }
    }
}
