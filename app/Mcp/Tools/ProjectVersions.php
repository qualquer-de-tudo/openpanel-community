<?php

namespace App\Mcp\Tools;

use App\Services\PanelAutomation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('project_versions')]
#[Description('Snapshot, list, inspect and restore immutable project configuration versions, optionally pinned to a 40-character Git commit SHA. Restores create a new revision; never rewrite history. plan_deploy saves a version-pinned plan blocked until a Linux executor exists. Credential values never enter manifests.')]
class ProjectVersions extends PanelTool
{
    protected array $allowed = ['action', 'resource_id', 'expected_revision', 'version_id', 'source_sha'];

    protected function execute(Request $request): array
    {
        $data = $request->validate(['action' => 'required|in:snapshot,list,inspect,restore,plan_deploy', 'resource_id' => 'required|integer|min:1', 'expected_revision' => 'required_if:action,snapshot,restore|integer|min:1', 'version_id' => 'required_if:action,inspect,restore,plan_deploy|uuid', 'source_sha' => ['nullable', 'string', 'regex:/^[a-f0-9]{40}$/D']]);
        $this->panel()->authorize(in_array($data['action'], ['list', 'inspect'], true) ? 'panel:read' : ($data['action'] === 'plan_deploy' ? 'deploy:plan' : 'versions:write'));

        return DB::transaction(function () use ($data) {
            $resource = $this->panel()->resource($data['resource_id'], true);
            if ($data['action'] === 'list') {
                return ['versions' => DB::table('project_versions')->where('user_id', Auth::id())->where('resource_id', $resource->id)->orderByDesc('revision')->limit(50)->get(['id', 'revision', 'digest', 'source_sha', 'created_at'])->all()];
            }
            if ($data['action'] === 'snapshot') {
                abort_unless($data['expected_revision'] === $resource->revision, 409);
                $manifest = $this->panel()->manifest($resource);
                $json = json_encode($manifest, JSON_THROW_ON_ERROR);
                $id = (string) Str::uuid();
                $previous = DB::table('project_versions')->where('resource_id', $resource->id)->orderByDesc('revision')->first();
                if ($previous && $previous->digest === hash('sha256', $json) && $previous->source_sha === ($data['source_sha'] ?? null)) {
                    return ['version_id' => $previous->id, 'revision' => $previous->revision, 'resource_revision' => $resource->revision, 'digest' => $previous->digest];
                }
                $number = ($previous?->revision ?? 0) + 1;
                DB::table('project_versions')->insert(['id' => $id, 'user_id' => Auth::id(), 'resource_id' => $resource->id, 'revision' => $number, 'manifest' => $json, 'digest' => hash('sha256', $json), 'source_sha' => $data['source_sha'] ?? null, 'created_at' => now()]);

                return ['version_id' => $id, 'revision' => $number, 'resource_revision' => $resource->revision, 'digest' => hash('sha256', $json)];
            }
            $version = DB::table('project_versions')->where('id', $data['version_id'])->where('resource_id', $resource->id)->where('user_id', Auth::id())->first();
            abort_unless($version, 404);
            $manifest = json_decode($version->manifest, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($version->digest, hash('sha256', $version->manifest)), 409);
            if ($data['action'] === 'inspect') {
                return ['version_id' => $version->id, 'revision' => $version->revision, 'digest' => $version->digest, 'source_sha' => $version->source_sha, 'manifest' => $manifest, 'current_revision' => $resource->revision, 'configuration_changed' => $manifest['configuration'] !== $this->panel()->manifest($resource)['configuration']];
            }
            if ($data['action'] === 'plan_deploy') {
                abort_unless($version->source_sha, 422);
                $id = (string) Str::uuid();
                DB::table('deployment_plans')->insert(['id' => $id, 'user_id' => Auth::id(), 'resource_id' => $resource->id, 'version_id' => $version->id, 'status' => 'blocked_agent', 'created_at' => now()]);

                return ['plan_id' => $id, 'version_id' => $version->id, 'source_sha' => $version->source_sha, 'status' => 'blocked_agent', 'executed' => false];
            }
            $this->panel()->authorize('panel:write');
            abort_unless($data['expected_revision'] === $resource->revision, 409);
            $currentBindings = $this->panel()->manifest($resource)['secret_bindings'];
            abort_unless($manifest['secret_bindings'] === $currentBindings, 409);
            $restored = $this->panel()->save($manifest['configuration'], $resource->id, $data['expected_revision']);

            return ['restored_from' => $version->id, 'resource' => $restored->only(PanelAutomation::FIELDS), 'source_checkout_executed' => false];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return ['action' => $schema->string()->enum(['snapshot', 'list', 'inspect', 'restore', 'plan_deploy'])->required(), 'resource_id' => $schema->integer()->min(1)->required(), 'expected_revision' => $schema->integer()->min(1), 'version_id' => $schema->string(), 'source_sha' => $schema->string()];
    }
}
