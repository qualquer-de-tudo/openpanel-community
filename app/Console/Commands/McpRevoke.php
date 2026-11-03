<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('panel:mcp-revoke {grant : Grant UUID}')]
#[Description('Immediately revoke a panel MCP grant')]
class McpRevoke extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Str::isUuid($this->argument('grant'))) {
            $this->error('ID inválido.');

            return self::FAILURE;
        }
        $updated = DB::table('mcp_grants')->where('id', $this->argument('grant'))->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        $this->info($updated ? 'Acesso revogado.' : 'Acesso inexistente ou já revogado.');

        return self::SUCCESS;
    }
}
