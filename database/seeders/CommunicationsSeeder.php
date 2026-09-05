<?php

namespace Database\Seeders;

use App\Models\EmailIdentity;
use App\Models\IntegrationProvider;
use Illuminate\Database\Seeder;

class CommunicationsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            IntegrationProvider::BREVO,
            IntegrationProvider::LARAVEL_MAIL,
            IntegrationProvider::SMARTSUPP,
            IntegrationProvider::JIVO,
            IntegrationProvider::CHATWAY,
            IntegrationProvider::GOOGLE_ANALYTICS,
            IntegrationProvider::MICROSOFT_CLARITY,
            IntegrationProvider::GOOGLE_IDENTITY,
        ] as $provider) {
            $row = IntegrationProvider::forProvider($provider);
            if ($provider === IntegrationProvider::GOOGLE_IDENTITY && empty($row->meta)) {
                $row->meta = \App\Services\Auth\Identity\GoogleIdentityProvider::defaultMeta();
                $row->save();
            }
        }

        $fromName = config('mail.from.name') ?: config('app.name', 'Social Growth Network');
        $fromEmail = config('mail.from.address') ?: 'noreply@example.com';

        $defaults = [
            ['profile' => 'general', 'from_name' => $fromName, 'from_email' => $fromEmail, 'is_default' => true],
            ['profile' => 'support', 'from_name' => 'Support Team', 'from_email' => $fromEmail, 'is_default' => false],
            ['profile' => 'sales', 'from_name' => 'Sales Team', 'from_email' => $fromEmail, 'is_default' => false],
            ['profile' => 'security', 'from_name' => 'Security', 'from_email' => $fromEmail, 'is_default' => false],
            ['profile' => 'billing', 'from_name' => 'Billing', 'from_email' => $fromEmail, 'is_default' => false],
            ['profile' => 'noreply', 'from_name' => $fromName, 'from_email' => $fromEmail, 'is_default' => false],
        ];

        foreach ($defaults as $row) {
            EmailIdentity::query()->firstOrCreate(
                ['profile' => $row['profile']],
                [
                    'from_name' => $row['from_name'],
                    'from_email' => $row['from_email'],
                    'is_default' => $row['is_default'],
                    'enabled' => true,
                ]
            );
        }

        // Seed laravel_mail.enabled from env once (when still at default idle/disabled).
        $laravel = IntegrationProvider::forProvider(IntegrationProvider::LARAVEL_MAIL);
        $wasTouched = filled($laravel->last_tested_at) || filled($laravel->last_success_at) || filled($laravel->last_error_at);
        if (! $wasTouched && ! $laravel->enabled) {
            $envMailer = (string) config('mail.default', '');
            $laravel->enabled = $envMailer !== '' && $envMailer !== 'array' && $envMailer !== 'log';
            $laravel->status = $laravel->enabled ? 'connected' : 'idle';
            $laravel->save();
        }
    }
}
