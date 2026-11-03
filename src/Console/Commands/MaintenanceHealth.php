<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use QOpenPanel\Community\Models\User;

class MaintenanceHealth extends Command
{
    protected $signature = 'qopenpanel:maintenance-health {version} {--job=} {--sha=}';

    protected $description = 'Validate the installed package, database and encryption before exposing an updated container';

    public function handle(): int
    {
        if (config('qopenpanel.release.version') !== ltrim($this->argument('version'), 'v') || ! is_file(config('qopenpanel.maintenance_ready_path', '/tmp/openpanel-ready'))) {
            return self::FAILURE;
        }
        try {
            if (DB::table('qopenpanel_users')->count() === 0) {
                return self::FAILURE;
            }
            if ($this->option('job')) {
                $job = DB::table('qopenpanel_maintenance_jobs')->where('id', $this->option('job'))->first();
                if (! $job || $job->action !== 'update' || $job->target !== 'v'.config('qopenpanel.release.version') || ! hash_equals($job->expected_sha ?? '', $this->option('sha') ?? '') || ! User::whereKey($job->user_id)->where('is_operator', true)->exists()) {
                    return self::FAILURE;
                }
            }
            $sample = DB::table('qopenpanel_vault_secrets')->value('ciphertext');
            if ($sample !== null) {
                Crypt::decryptString($sample);
            }

            return Crypt::decryptString(Crypt::encryptString('health')) === 'health' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            return self::FAILURE;
        }
    }
}
