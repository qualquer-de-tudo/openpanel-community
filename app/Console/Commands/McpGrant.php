<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PanelAutomation;
use App\Services\PrivateFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('panel:mcp-grant {email} {output : New private client JSON file} {--endpoint=http://127.0.0.1:8080/mcp/panel} {--scopes=panel:read} {--projects= : Comma-separated resource IDs} {--days=7}')]
#[Description('Create a scoped, expiring MCP grant in a private file; never print the credential')]
class McpGrant extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $scopes = explode(',', $this->option('scopes'));
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
        $endpoint = $this->option('endpoint');
        $url = parse_url($endpoint);
        if (! $user || ! $days || array_diff($scopes, PanelAutomation::SCOPES) || ! $url || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || ($url['path'] ?? '') !== '/mcp/panel' || (($url['scheme'] ?? '') !== 'https' && ! (($url['scheme'] ?? '') === 'http' && in_array($url['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)))) {
            $this->error('Conta, permissões, endpoint ou validade inválidos.');

            return self::FAILURE;
        }
        $ids = null;
        if ($this->option('projects') !== null) {
            $raw = explode(',', $this->option('projects'));
            foreach ($raw as $id) {
                if (! ctype_digit($id) || ((int) $id) < 1) {
                    $this->error('IDs de projetos inválidos.');

                    return self::FAILURE;
                }
            }
            $ids = array_values(array_unique(array_map('intval', $raw)));
            if (\App\Models\Resource::where('user_id', $user->id)->whereIn('id', $ids)->count() !== count($ids)) {
                $this->error('Projetos não disponíveis para esta conta.');

                return self::FAILURE;
            }
        }
        $id = (string) Str::uuid();
        $token = 'qpm_'.bin2hex(random_bytes(48));
        $expires = now()->addDays($days);
        try {
            DB::table('mcp_grants')->insert(['id' => $id, 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'scopes' => json_encode(array_values(array_unique($scopes))), 'resource_ids' => $ids === null ? null : json_encode($ids), 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
            $json = json_encode(['endpoint' => $endpoint, 'token' => $token, 'grant_id' => $id, 'expires_at' => $expires->toIso8601String()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            PrivateFile::write($this->argument('output'), $json);
        } catch (\Throwable) {
            DB::table('mcp_grants')->where('id', $id)->delete();
            $this->error('Não foi possível salvar a concessão. Nenhuma credencial foi exibida.');

            return self::FAILURE;
        }
        $this->info('Acesso criado. ID para revogação: '.$id);
        $this->line('Credencial gravada no arquivo privado; não cole seu conteúdo em chats.');

        return self::SUCCESS;
    }
}
