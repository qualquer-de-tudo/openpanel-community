<?php

namespace App\Mcp\Tools;

use App\Services\TemplateCatalog;
use App\Services\TemplateInstaller;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('project_templates')]
#[Description('Gerencie templates versionados: catálogo, instalação, atualização e rollback. Retorna metadados e histórico, nunca segredos. A instalação cria um projeto exportável; não executa scripts nem deploys. Escrita exige panel:write e versions:write.')]
class ManageTemplates extends PanelTool
{
    protected array $allowed = ['action', 'template_id', 'version', 'name', 'domain', 'resource_id', 'expected_revision', 'history_id'];

    protected function execute(Request $request): array
    {
        $data = $request->validate(['action' => 'required|in:catalog,inspect,install,update,rollback', 'template_id' => 'required_if:action,install|string|max:64', 'version' => ['required_if:action,install,update', 'string', 'regex:'.TemplateCatalog::VERSION], 'name' => ['required_if:action,install', 'string', 'max:63', 'regex:/^[a-z0-9][a-z0-9._-]*$/D'], 'domain' => 'nullable|string|max:253', 'resource_id' => 'required_if:action,inspect,update,rollback|integer|min:1', 'expected_revision' => 'required_if:action,update,rollback|integer|min:1', 'history_id' => 'required_if:action,rollback|uuid']);
        $installer = app(TemplateInstaller::class);
        if (in_array($data['action'], ['catalog', 'inspect'], true)) {
            $this->panel()->authorize('panel:read');
        }

        return match ($data['action']) {
            'catalog' => ['templates' => app(TemplateCatalog::class)->summaries()],'inspect' => $installer->inspect($data['resource_id']),'install' => $installer->install($data['template_id'], $data['version'], $data['name'], $data['domain'] ?? null),'update' => $installer->change($data['resource_id'], $data['expected_revision'], $data['version']),'rollback' => $installer->change($data['resource_id'], $data['expected_revision'], historyId: $data['history_id'])
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return ['action' => $schema->string()->enum(['catalog', 'inspect', 'install', 'update', 'rollback'])->required(), 'template_id' => $schema->string(), 'version' => $schema->string(), 'name' => $schema->string(), 'domain' => $schema->string(), 'resource_id' => $schema->integer()->min(1), 'expected_revision' => $schema->integer()->min(1), 'history_id' => $schema->string()];
    }
}
