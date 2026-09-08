<?php

namespace App\Console\Commands;

use App\Services\Branding\PwaBrandingSync;
use App\Services\Branding\SiteBrandingRepository;
use Illuminate\Console\Command;

class SyncPwaBrandingCommand extends Command
{
    protected $signature = 'branding:sync-pwa';

    protected $description = 'Regenerate favicon, Apple touch, PWA (any + maskable), and OG icons from admin branding media';

    public function handle(PwaBrandingSync $sync, SiteBrandingRepository $branding): int
    {
        $branding->flush();
        $settings = $branding->all();
        $hasMedia = (int) ($settings['favicon_media_id'] ?? 0) > 0
            || (int) ($settings['logo_light_media_id'] ?? 0) > 0
            || (int) ($settings['logo_dark_media_id'] ?? 0) > 0;

        if (! $hasMedia) {
            $this->warn('No favicon/logo media configured in Admin → Settings. Existing public icons will be preserved (letter-7 will not overwrite them).');
        } else {
            $this->line(sprintf(
                'Using branding media ids: favicon=%s logo_light=%s logo_dark=%s',
                $settings['favicon_media_id'] ?? 'null',
                $settings['logo_light_media_id'] ?? 'null',
                $settings['logo_dark_media_id'] ?? 'null'
            ));
        }

        if (! $sync->sync($settings)) {
            $detail = $sync->lastError() ?: 'unknown error';
            $this->error('Branding icon sync failed: '.$detail);
            $this->line('Common fixes: php artisan storage:link ; enable PHP GD ; chmod public/ and public/icons writable.');

            return self::FAILURE;
        }

        $this->info('Branding icons and manifest.json updated.');

        foreach ($sync->headIconUrls($settings) as $key => $url) {
            $this->line("  {$key}: {$url}");
        }

        return self::SUCCESS;
    }
}
