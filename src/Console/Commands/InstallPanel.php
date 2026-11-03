<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use QOpenPanel\Community\Models\User;
use QOpenPanel\Community\Providers\QOpenPanelServiceProvider;

class InstallPanel extends Command
{
    protected $signature = 'qopenpanel:install {--force : Run package migrations in production} {--check : Check that an initial account exists without changing anything} {--check-legacy : Detect the old SQLite panel without changing anything}';

    protected $description = 'Install the isolated QOpenPanel module without replacing host files';

    public function handle(): int
    {
        if ($this->option('check-legacy')) {
            return ImportLegacyPanel::recognized() ? self::SUCCESS : self::FAILURE;
        }
        if ($this->option('check')) {
            return Schema::hasTable('qopenpanel_users') && User::exists() ? self::SUCCESS : self::FAILURE;
        }
        $this->call('vendor:publish', ['--tag' => 'qopenpanel-config']);
        $this->call('vendor:publish', ['--tag' => 'qopenpanel-assets', '--force' => true]);
        $status = $this->call('migrate', ['--path' => QOpenPanelServiceProvider::path('database/package-migrations'), '--realpath' => true, '--force' => $this->option('force')]);
        if ($status !== 0) {
            return self::FAILURE;
        }
        $this->info('QOpenPanel: '.url(trim(config('qopenpanel.path'), '/').'/login'));
        $this->line('Create the first account through a private SSH tunnel, or run panel:account. No default password.');

        return self::SUCCESS;
    }
}
