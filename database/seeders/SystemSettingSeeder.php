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
            'withdrawal_min_amount' => '10',
            'withdrawal_max_amount' => '5000',
            'deposit_min_amount' => '1',
            'live_chat_provider' => 'none',
            'smartsupp_key' => '',
            'jivo_widget_id' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_email_alt' => '',
            'site_name' => config('app.name', 'Social Growth Network'),
            'site_short_name' => 'Social Growth',
            'site_heading' => 'Boost your YouTube visibility',
            'site_tagline' => 'YouTube promotion campaigns that help creators get seen by more viewers.',
            'site_meta_description' => 'Social Growth Network helps creators boost YouTube visibility, reach and engagement with clear, fixed-price promotion campaigns. No bots, fake views or fake subscribers.',
            'contact_timezone' => 'Africa/Lagos',
        ];

        foreach ($defaults as $key => $value) {
            if (SystemSetting::where('key', $key)->doesntExist()) {
                SystemSetting::set($key, $value);
            }
        }
    }
}
