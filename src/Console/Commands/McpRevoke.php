<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('panel:mcp-revoke {grant : Grant UUID}')]
#[Description('Immediately revoke a panel MCP grant')]
class McpRevoke extends Command
{
    public function getDescription(): string
    {
        return __(parent::getDescription());
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Str::isUuid($this->argument('grant'))) {
            $this->error(__('ID inválido.'));

            return self::FAILURE;
        }
        $updated = DB::table('qopenpanel_mcp_grants')->where('id', $this->argument('grant'))->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        $this->info($updated ? __('Acesso revogado.') : __('Acesso inexistente ou já revogado.'));

        return self::SUCCESS;
    }
}
