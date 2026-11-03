<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportLegacyPanel extends Command
{
    public static function recognized(): bool
    {
        $markers = ['2026_11_03_074212_create_resources_table', '2026_11_03_093803_create_mcp_security_tables', '2026_11_03_103013_create_template_installations_tables'];

        return DB::getDriverName() === 'sqlite' && Schema::hasTable('migrations') && DB::table('migrations')->whereIn('migration', $markers)->count() === 3;
    }

    protected $signature = 'qopenpanel:import-legacy {--force : Confirm a backed-up 0.4 installation with the same APP_KEY}';

    protected $description = 'Copy a legacy SQLite panel into isolated module tables; never remove original data';

    public function handle(): int
    {
        $tables = ['users', 'accounts', 'resources', 'mcp_grants', 'vault_secrets', 'secret_bindings', 'project_versions', 'mcp_audit', 'deployment_plans', 'template_installations', 'template_installation_history'];
        if (! $this->option('force') || ! static::recognized()) {
            $this->error('Requires --force and a recognized 0.4 SQLite installation. Preserve APP_KEY and back up first.');

            return self::FAILURE;
        }
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasTable('qopenpanel_'.$table) || DB::table('qopenpanel_'.$table)->exists()) {
                $this->error('Source tables must exist and destination tables must be empty.');

                return self::FAILURE;
            }
        }
        DB::transaction(function () use ($tables) {
            DB::table('qopenpanel_setup')->where('id', 1)->lockForUpdate()->first();
            foreach ($tables as $table) {
                if (DB::table('qopenpanel_'.$table)->exists()) {
                    throw new \RuntimeException('Destination changed; import cancelled.');
                }
                DB::table($table)->orderBy('id')->chunk(100, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table('qopenpanel_'.$table)->insert((array) $row);
                    }
                });
            }
        });
        $this->info('Legacy data copied. Source tables preserved. Verify the module before switching traffic.');

        return self::SUCCESS;
    }
}
