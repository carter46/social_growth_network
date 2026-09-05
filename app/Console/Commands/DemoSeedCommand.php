<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Demo\DemoBatchTracker;
use App\Support\Demo\DemoGate;
use Database\Seeders\Demo\DemoPlatformSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed {--force : Skip confirmation}';

    protected $description = 'Seed realistic demo data into the current DB (no wipe). Requires ALLOW_DEMO_DATA=true.';

    public function handle(DemoBatchTracker $tracker): int
    {
        try {
            DemoGate::assertCanSeed();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (User::query()->where('email', 'alice@example.com')->exists()) {
            $this->error('Demo personas already exist. Run `php artisan demo:clear --force` first, or `demo:fresh` to wipe.');

            return self::FAILURE;
        }

        $this->warn('This will insert demo users, KYC, tickets, transactions, and analytics.');
        $this->line('Database: '.(string) config('database.connections.'.config('database.default').'.database'));
        $this->line('APP_ENV: '.app()->environment());

        if (! $this->option('force')) {
            if (! $this->confirm('Continue?', false)) {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }
            if (strtoupper((string) $this->ask('Type YES to confirm')) !== 'YES') {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }
        }

        $batch = $tracker->start('Demo seed '.now()->toDateTimeString(), 'demo:seed');

        $this->call('db:seed', [
            '--class' => DemoPlatformSeeder::class,
            '--force' => true,
        ]);

        $this->info("Demo seed complete (batch #{$batch->id}).");
        $this->line('Launch cleanup: php artisan demo:clear --force && php artisan analytics:rollup-kpis');

        return self::SUCCESS;
    }
}
