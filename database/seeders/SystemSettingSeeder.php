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
            'site_tagline' => 'Creators launch digital campaigns. Agents complete verified tasks. Secure payment and payouts in one place.',
            'site_meta_description' => 'Social Growth Network connects Creators and Agents for campaign packages, proof verification, wallet funding, and secure payouts.',
            'contact_timezone' => 'Africa/Lagos',
        ];

        foreach ($defaults as $key => $value) {
            if (SystemSetting::where('key', $key)->doesntExist()) {
                SystemSetting::set($key, $value);
            }
        }
    }
}
