<?php

namespace App\Mcp\Tools;

use App\Services\PanelAutomation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('panel_configure')]
#[Description('Create, update or delete a planned resource in the authenticated workspace. Updates require all configuration fields and expected_revision. All service categories are supported. This does not install software. Never include passwords or tokens; use vault references.')]
class ManagePanelResource extends PanelTool
{
    protected array $allowed = ['action', 'resource_id', 'expected_revision', 'kind', 'name', 'engine', 'domain', 'repository', 'disk_mb'];

    protected function execute(Request $request): array
    {
        $data = $request->validate(['action' => 'required|in:create,update,delete', 'resource_id' => 'required_unless:action,create|integer|min:1', 'expected_revision' => 'required_unless:action,create|integer|min:1']);
        if ($data['action'] === 'delete') {
            $this->panel()->remove($data['resource_id'], $data['expected_revision']);

            return ['deleted' => true];
        }
        $resource = $this->panel()->save($request->all(), $data['resource_id'] ?? null, $data['expected_revision'] ?? null);

        return ['resource' => $resource->only(PanelAutomation::FIELDS), 'execution' => 'planned'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['action' => $schema->string()->enum(['create', 'update', 'delete'])->required(), 'resource_id' => $schema->integer()->min(1), 'expected_revision' => $schema->integer()->min(1), 'kind' => $schema->string()->enum(array_keys(config('catalog'))), 'name' => $schema->string(), 'engine' => $schema->string(), 'domain' => $schema->string(), 'repository' => $schema->string(), 'disk_mb' => $schema->integer()->min(64)];
    }
}
