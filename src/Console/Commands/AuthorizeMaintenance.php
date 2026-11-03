<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use QOpenPanel\Community\Models\User;

class AuthorizeMaintenance extends Command
{
    protected $signature = 'qopenpanel:maintenance-authorize {id} {action} {target} {sha}';

    protected $description = 'Verify a queued maintenance request without exporting any private data';

    public function handle(): int
    {
        $job = DB::table('qopenpanel_maintenance_jobs')->where('id', $this->argument('id'))->first();
        $valid = $job && ! $job->dispatch_failed && $job->action === $this->argument('action') && ($job->target ?? '-') === $this->argument('target') && ($job->expected_sha ?? '-') === $this->argument('sha') && User::whereKey($job->user_id)->where('is_operator', true)->exists();

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
