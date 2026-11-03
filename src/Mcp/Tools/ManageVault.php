<?php

namespace QOpenPanel\Community\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use QOpenPanel\Community\Services\BlindVault;

#[Name('vault_manage')]
#[Description('Generate cryptographically random application credentials, rotate, bind or unbind environment variables, and list metadata. Returns opaque UUID references only. No operation reads or exports secrets. External provider credentials must be imported by the human operator outside MCP.')]
class ManageVault extends PanelTool
{
    protected array $allowed = ['action', 'resource_id', 'reference', 'variable'];

    protected function execute(Request $request): array
    {
        $this->panel()->authorize('secrets:manage');
        $data = $request->validate(['action' => 'required|in:list,generate,rotate,bind,unbind', 'resource_id' => 'required|integer|min:1', 'reference' => 'required_if:action,rotate,bind|uuid', 'variable' => ['required_if:action,bind,unbind', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/D']]);
        $this->panel()->resource($data['resource_id']);
        $vault = app(BlindVault::class);
        if (in_array($data['action'], ['rotate', 'bind'], true)) {
            $secret = $vault->find($data['reference']);
            abort_unless($secret->resource_id === $data['resource_id'], 403);
        }

        return match ($data['action']) {
            'generate' => $vault->generate($data['resource_id']),
            'rotate' => $vault->rotate($data['reference']),
            'bind','unbind' => $vault->bind($data['resource_id'], $data['variable'], $data['action'] === 'bind' ? $data['reference'] : null),
            'list' => ['secrets' => DB::table('qopenpanel_vault_secrets')->where('user_id', Auth::guard('qopenpanel')->id())->where('resource_id', $data['resource_id'])->get(['id', 'purpose', 'version', 'created_at', 'updated_at'])->all(), 'bindings' => DB::table('qopenpanel_secret_bindings')->where('resource_id', $data['resource_id'])->get(['variable', 'secret_id'])->all()],
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return ['action' => $schema->string()->enum(['list', 'generate', 'rotate', 'bind', 'unbind'])->required(), 'resource_id' => $schema->integer()->min(1)->required(), 'reference' => $schema->string()->description(__('Opaque secret UUID, never a secret value.')), 'variable' => $schema->string()->description(__('Environment variable name, e.g. DB_PASSWORD.'))];
    }
}
