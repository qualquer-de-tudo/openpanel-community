<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateInstaller
{
    public function __construct(private TemplateCatalog $catalog, private PanelAutomation $panel, private ProjectVersionStore $versions) {}

    public function install(string $template, string $version, string $name, ?string $domain = null): array
    {
        $this->panel->authorize('panel:write');
        $this->panel->authorize('versions:write');
        $release = $this->catalog->release($template, $version);
        $this->compatible($release['manifest']);

        return DB::transaction(function () use ($release, $name, $domain) {
            $manifest = $release['manifest'];
            $resource = $this->panel->save(['kind' => 'sites', 'name' => $name, 'domain' => $domain, 'engine' => $manifest['engine'], 'disk_mb' => $manifest['disk_mb'], 'repository' => null]);
            $id = (string) Str::uuid();
            DB::table('template_installations')->insert(['id' => $id, 'user_id' => Auth::id(), 'resource_id' => $resource->id, 'template_id' => $manifest['id'], 'version' => $manifest['version'], 'digest' => $release['digest'], 'manifest' => $this->catalog->canonical($manifest), 'created_at' => now(), 'updated_at' => now()]);
            $snapshot = $this->versions->capture($resource);
            $this->history($id, $release, 'install', $snapshot['version_id']);
            $this->panel->audit('template_install', 'success');

            return ['installation_id' => $id, 'resource_id' => $resource->id, 'resource_revision' => $resource->revision, 'version' => $manifest['version'], 'digest' => $release['digest'], 'project_version_id' => $snapshot['version_id'], 'execution' => 'scaffold_only'];
        });
    }

    public function installation(int $resourceId, bool $lock = false): object
    {
        $this->panel->resource($resourceId);
        $query = DB::table('template_installations')->where('user_id', Auth::id())->where('resource_id', $resourceId);
        if ($lock) {
            $query->lockForUpdate();
        }$installation = $query->first();
        abort_unless($installation, 404);

        return $installation;
    }

    public function change(int $resourceId, int $expected, ?string $version = null, ?string $historyId = null): array
    {
        $this->panel->authorize('panel:write');
        $this->panel->authorize('versions:write');

        return DB::transaction(function () use ($resourceId, $expected, $version, $historyId) {
            DB::table('accounts')->where('user_id', Auth::id())->lockForUpdate()->first();
            $resource = $this->panel->resource($resourceId, true);
            abort_unless($resource->revision === $expected, 409);
            $installation = $this->installation($resourceId, true);
            if ($historyId) {
                $historical = DB::table('template_installation_history')->where('id', $historyId)->where('installation_id', $installation->id)->where('user_id', Auth::id())->first();
                abort_unless($historical, 404);
                $manifest = json_decode($historical->manifest, true, 512, JSON_THROW_ON_ERROR);
                $release = ['manifest' => $this->catalog->validate($manifest), 'digest' => $historical->digest];
                abort_unless(hash_equals($historical->digest, hash('sha256', $this->catalog->canonical($manifest))), 409);
                $action = 'rollback';
            } else {
                $release = $this->catalog->release($installation->template_id, $version);
                abort_unless(version_compare($version, $installation->version, '>'), 422);
                $action = 'update';
            }
            $this->compatible($release['manifest']);
            abort_unless($release['manifest']['id'] === $installation->template_id && $release['manifest']['engine'] === $resource->engine, 422);
            $resource = $this->panel->save([...$resource->only(['kind', 'name', 'domain', 'engine', 'repository', 'disk_mb']), 'disk_mb' => max($resource->disk_mb, $release['manifest']['disk_mb'])], $resourceId, $expected);
            DB::table('template_installations')->where('id', $installation->id)->update(['version' => $release['manifest']['version'], 'digest' => $release['digest'], 'manifest' => $this->catalog->canonical($release['manifest']), 'updated_at' => now()]);
            $snapshot = $this->versions->capture($resource);
            $this->history($installation->id, $release, $action, $snapshot['version_id']);
            $this->panel->audit('template_'.$action, 'success');

            return ['resource_id' => $resourceId, 'resource_revision' => $resource->revision, 'version' => $release['manifest']['version'], 'digest' => $release['digest'], 'project_version_id' => $snapshot['version_id'], 'execution' => 'scaffold_only'];
        });
    }

    public function inspect(int $resourceId): array
    {
        $installation = $this->installation($resourceId);
        $manifest = json_decode($installation->manifest, true, 512, JSON_THROW_ON_ERROR);

        return ['installation' => ['id' => $installation->id, 'resource_id' => $resourceId, 'template_id' => $installation->template_id, 'version' => $installation->version, 'digest' => $installation->digest, 'file_paths' => array_keys($manifest['files'])], 'history' => DB::table('template_installation_history')->where('installation_id', $installation->id)->where('user_id', Auth::id())->orderByDesc('sequence')->limit(50)->get(['id', 'sequence', 'action', 'version', 'digest', 'project_version_id', 'created_at'])->all()];
    }

    private function compatible(array $manifest): void
    {
        abort_unless(version_compare(config('release.version'), $manifest['panel_min'], '>='), 422);
    }

    private function history(string $id, array $release, string $action, string $projectVersionId): void
    {
        $sequence = (DB::table('template_installation_history')->where('installation_id', $id)->max('sequence') ?? 0) + 1;
        DB::table('template_installation_history')->insert(['id' => (string) Str::uuid(), 'installation_id' => $id, 'user_id' => Auth::id(), 'sequence' => $sequence, 'action' => $action, 'version' => $release['manifest']['version'], 'digest' => $release['digest'], 'manifest' => $this->catalog->canonical($release['manifest']), 'project_version_id' => $projectVersionId, 'created_at' => now()]);
    }
}
