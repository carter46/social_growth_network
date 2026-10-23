<?php

namespace App\Console\Commands;

use App\Support\SandboxCutover;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** Mark the go-live date so analytics exclude sandbox-era paid orders. */
class SetSandboxCutoverCommand extends Command
{
    protected $signature = 'analytics:set-sandbox-cutover {--before= : Y-m-d go-live date}';

    protected $description = 'Exclude paid orders at/before this date from platform revenue and GMV';

    public function handle(): int
    {
        $raw = (string) $this->option('before');
        if ($raw === '') {
            $this->error('Pass --before=YYYY-MM-DD (e.g. 2026-10-22).');

            return self::FAILURE;
        }

        try {
            $before = Carbon::parse($raw);
        } catch (\Throwable) {
            $this->error('Could not parse --before.');

            return self::FAILURE;
        }

        SandboxCutover::remember($before);
        $this->info('Sandbox cutover set to '.$before->toDateString().'. Revenue analytics will ignore paid orders on or before that day.');

        return self::SUCCESS;
    }
}
