<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use App\Services\PanelAutomation;
use App\Services\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PanelMcpTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        $u = User::factory()->create();
        DB::table('accounts')->insert(['user_id' => $u->id, 'disk_mb' => 2048, 'memory_mb' => 512, 'project_limit' => 2, 'database_limit' => 1]);

        return $u;
    }

    private function resource(User $user, string $name = 'project'): Resource
    {
        return Resource::create(['user_id' => $user->id, 'kind' => 'sites', 'name' => $name, 'engine' => 'Laravel', 'repository' => 'https://github.com/example/app', 'disk_mb' => 512, 'revision' => 1]);
    }

    private function grant(User $user, array $scopes = PanelAutomation::SCOPES, ?array $ids = null): array
    {
        $token = 'qpm_'.bin2hex(random_bytes(48));
        $id = (string) Str::uuid();
        DB::table('mcp_grants')->insert(['id' => $id, 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'scopes' => json_encode($scopes), 'resource_ids' => $ids === null ? null : json_encode($ids), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);

        return [$token, $id];
    }

    private function rpc(string $token, string $method, array $params = []): TestResponse
    {
        return $this->postJson('/mcp/panel', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], ['Authorization' => 'Bearer '.$token, 'MCP-Protocol-Version' => '2025-11-25', 'Mcp-Method' => $method]);
    }

    private function callTool(string $token, string $name, array $arguments = []): TestResponse
    {
        return $this->rpc($token, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    private function toolResult(TestResponse $response): array
    {
        $response->assertOk()->assertJsonPath('result.isError', false);

        return json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function githubSecret(User $user, Resource $resource): string
    {
        $id = (string) Str::uuid();
        DB::table('vault_secrets')->insert(['id' => $id, 'user_id' => $user->id, 'resource_id' => $resource->id, 'purpose' => 'github', 'repository' => $resource->repository, 'ciphertext' => Crypt::encryptString('github_private_fixture_123456789'), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_mcp_requires_its_own_token_even_for_signed_in_users(): void
    {
        $this->actingAs($this->account())->postJson('/mcp/panel', ['method' => 'tools/list'])->assertUnauthorized()->assertJsonPath('error', 'invalid_mcp_access');
    }

    public function test_expired_and_revoked_grants_return_401(): void
    {
        $this->freezeTime();
        [$token,$id] = $this->grant($this->account());
        $this->travel(2)->hours();
        $this->rpc($token, 'tools/list')->assertUnauthorized();
        $this->travelBack();
        DB::table('mcp_grants')->where('id', $id)->update(['revoked_at' => now()]);
        $this->rpc($token, 'tools/list')->assertUnauthorized();
    }

    public function test_browser_origins_are_denied(): void
    {
        [$token] = $this->grant($this->account());
        $this->withHeader('Origin', 'https://evil.example')->rpc($token, 'tools/list')->assertForbidden()->assertJsonPath('error', 'browser_origins_not_allowed');
    }

    public function test_initialize_and_discovery_use_real_mcp_protocol(): void
    {
        [$token] = $this->grant($this->account());
        $this->rpc($token, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])->assertOk()->assertJsonPath('result.serverInfo.name', 'QOpenPanel Community');
        $this->rpc($token, 'tools/list')->assertOk()->assertJsonCount(5, 'result.tools');
    }

    public function test_inspection_is_restricted_to_account_and_granted_projects(): void
    {
        $user = $this->account();
        $a = $this->resource($user, 'visible');
        $b = $this->resource($user, 'hidden');
        $other = $this->resource($this->account(), 'foreign');
        [$token] = $this->grant($user, ['panel:read'], [$a->id]);
        $data = $this->toolResult($this->callTool($token, 'panel_inspect'));
        $this->assertCount(1, $data['resources']);
        $this->assertSame('visible', $data['resources'][0]['name']);
        $this->assertFalse($data['capabilities']['credential_read']);
        $this->callTool($token, 'panel_configure', ['action' => 'delete', 'resource_id' => $other->id, 'expected_revision' => 1])->assertOk()->assertJsonPath('result.content.0.text', 'forbidden');
    }

    #[TestWith(['panel_configure', ['action' => 'create', 'kind' => 'sites', 'name' => 'test', 'engine' => 'Laravel', 'disk_mb' => 512]])]
    #[TestWith(['vault_manage', ['action' => 'generate', 'resource_id' => 1]])]
    #[TestWith(['project_versions', ['action' => 'snapshot', 'resource_id' => 1, 'expected_revision' => 1]])]
    public function test_read_only_grants_refuse_each_write_category(string $tool, array $arguments): void
    {
        [$token] = $this->grant($this->account(), ['panel:read']);
        $this->callTool($token, $tool, $arguments)->assertOk()->assertJsonPath('result.content.0.text', 'forbidden');
        $this->assertDatabaseCount('vault_secrets', 0);
        $this->assertDatabaseCount('project_versions', 0);
    }

    public function test_configuration_crud_uses_quotas_and_expected_revisions(): void
    {
        $user = $this->account();
        [$token] = $this->grant($user);
        $config = ['action' => 'create', 'kind' => 'sites', 'name' => 'created', 'engine' => 'Laravel', 'disk_mb' => 512];
        $data = $this->toolResult($this->callTool($token, 'panel_configure', $config));
        $id = $data['resource']['id'];
        $this->assertDatabaseHas('resources', ['id' => $id, 'status' => 'planned', 'revision' => 1]);
        $this->callTool($token, 'panel_configure', [...$config, 'name' => 'too-large', 'disk_mb' => 3000])->assertOk()->assertJsonPath('result.content.0.text', 'validation_failed');
        $updated = $this->toolResult($this->callTool($token, 'panel_configure', [...$config, 'action' => 'update', 'resource_id' => $id, 'expected_revision' => 1, 'name' => 'updated']));
        $this->assertSame(2, $updated['resource']['revision']);
        $this->callTool($token, 'panel_configure', [...$config, 'action' => 'update', 'resource_id' => $id, 'expected_revision' => 1])->assertOk()->assertJsonPath('result.content.0.text', 'revision_or_state_conflict');
        $this->toolResult($this->callTool($token, 'panel_configure', ['action' => 'delete', 'resource_id' => $id, 'expected_revision' => 2]));
        $this->assertDatabaseMissing('resources', ['id' => $id]);
    }

    public function test_cross_account_resource_write_and_secret_access_are_not_found(): void
    {
        $first = $this->account();
        $foreign = $this->resource($this->account());
        [$token] = $this->grant($first);
        $this->callTool($token, 'panel_configure', ['action' => 'delete', 'resource_id' => $foreign->id, 'expected_revision' => 1])->assertOk()->assertJsonPath('result.content.0.text', 'not_found');
        $this->callTool($token, 'vault_manage', ['action' => 'list', 'resource_id' => $foreign->id])->assertOk()->assertJsonPath('result.content.0.text', 'not_found');
        $this->assertDatabaseHas('resources', ['id' => $foreign->id]);
    }

    public function test_generated_secrets_are_encrypted_bound_and_never_returned_in_tools_or_audit(): void
    {
        $user = $this->account();
        $resource = $this->resource($user);
        [$token] = $this->grant($user);
        $created = $this->callTool($token, 'vault_manage', ['action' => 'generate', 'resource_id' => $resource->id]);
        $reference = $this->toolResult($created)['reference'];
        $row = DB::table('vault_secrets')->where('id', $reference)->first();
        $plain = Crypt::decryptString($row->ciphertext);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{96}$/', $plain);
        $this->assertNotSame($plain, $row->ciphertext);
        $created->assertDontSee($plain, false);
        $bound = $this->toolResult($this->callTool($token, 'vault_manage', ['action' => 'bind', 'resource_id' => $resource->id, 'reference' => $reference, 'variable' => 'DB_PASSWORD']));
        $this->assertSame(2, $bound['revision']);
        $this->assertDatabaseHas('secret_bindings', ['secret_id' => $reference, 'variable' => 'DB_PASSWORD']);
        $list = $this->callTool($token, 'vault_manage', ['action' => 'list', 'resource_id' => $resource->id]);
        $list->assertDontSee($plain, false)->assertDontSee($row->ciphertext, false);
        $this->callTool($token, 'vault_manage', ['action' => 'read', 'resource_id' => $resource->id, 'reference' => $reference])->assertJsonPath('result.content.0.text', 'validation_failed');
        $rotated = $this->toolResult($this->callTool($token, 'vault_manage', ['action' => 'rotate', 'resource_id' => $resource->id, 'reference' => $reference]));
        $this->assertSame(2, $rotated['version']);
        $this->assertNotSame($plain, Crypt::decryptString(DB::table('vault_secrets')->where('id', $reference)->value('ciphertext')));
        $this->assertStringNotContainsString($plain, json_encode(DB::table('mcp_audit')->get()));
    }

    public function test_unknown_fields_cannot_override_owner_or_supply_credentials(): void
    {
        $user = $this->account();
        $resource = $this->resource($user);
        [$token] = $this->grant($user);
        $this->callTool($token, 'vault_manage', ['action' => 'generate', 'resource_id' => $resource->id, 'value' => 'plaintext-not-allowed'])->assertJsonPath('result.content.0.text', 'validation_failed');
        $this->callTool($token, 'panel_configure', ['action' => 'create', 'user_id' => 999])->assertJsonPath('result.content.0.text', 'validation_failed');
        $this->assertDatabaseCount('vault_secrets', 0);
        $this->assertStringNotContainsString('plaintext-not-allowed', json_encode(DB::table('mcp_audit')->get()));
    }

    public function test_versions_remain_immutable_and_restore_creates_new_revision(): void
    {
        $user = $this->account();
        $resource = $this->resource($user);
        [$token] = $this->grant($user);
        $sha = str_repeat('a', 40);
        $snapshot = $this->toolResult($this->callTool($token, 'project_versions', ['action' => 'snapshot', 'resource_id' => $resource->id, 'expected_revision' => 1, 'source_sha' => $sha]));
        $resource->update(['name' => 'changed', 'revision' => 2]);
        $restored = $this->toolResult($this->callTool($token, 'project_versions', ['action' => 'restore', 'resource_id' => $resource->id, 'expected_revision' => 2, 'version_id' => $snapshot['version_id']]));
        $this->assertSame('project', $restored['resource']['name']);
        $this->assertSame(3, $restored['resource']['revision']);
        $this->assertDatabaseHas('project_versions', ['id' => $snapshot['version_id'], 'revision' => 1]);
        $plan = $this->toolResult($this->callTool($token, 'project_versions', ['action' => 'plan_deploy', 'resource_id' => $resource->id, 'version_id' => $snapshot['version_id']]));
        $this->assertFalse($plan['executed']);
        $this->assertSame('blocked_agent', $plan['status']);
        $this->assertDatabaseHas('deployment_plans', ['id' => $plan['plan_id'], 'version_id' => $snapshot['version_id']]);
    }

    public function test_github_uses_internal_token_and_discards_provider_error_bodies(): void
    {
        Http::preventStrayRequests();
        $user = $this->account();
        $resource = $this->resource($user);
        $reference = $this->githubSecret($user, $resource);
        [$token] = $this->grant($user);
        Http::fake(['https://api.github.com/repos/example/app/branches*' => Http::sequence()->push([['name' => 'main', 'commit' => ['sha' => str_repeat('a', 40)]]])->push(['message' => 'github_private_fixture_123456789'], 500)]);
        $data = $this->toolResult($this->callTool($token, 'git_repository', ['action' => 'branches', 'resource_id' => $resource->id, 'reference' => $reference]));
        $this->assertSame('main', $data['branches'][0]['name']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer github_private_fixture_123456789'));
        $failed = $this->callTool($token, 'git_repository', ['action' => 'branches', 'resource_id' => $resource->id, 'reference' => $reference]);
        $failed->assertJsonPath('result.content.0.text', 'forbidden')->assertDontSee('github_private_fixture_123456789', false);
    }

    public function test_github_credentials_cannot_follow_repository_repointing(): void
    {
        Http::preventStrayRequests();
        $user = $this->account();
        $resource = $this->resource($user);
        $reference = $this->githubSecret($user, $resource);
        $resource->update(['repository' => 'https://github.com/attacker/other']);
        [$token] = $this->grant($user);
        $this->callTool($token, 'git_repository', ['action' => 'branches', 'resource_id' => $resource->id, 'reference' => $reference])->assertJsonPath('result.content.0.text', 'forbidden');
        Http::assertNothingSent();
    }

    #[TestWith(['.github/workflows/deploy.yml'])]
    #[TestWith(['src/../credentials.json'])]
    #[TestWith(['.env'])]
    public function test_git_rejects_workflow_and_credential_paths(string $path): void
    {
        Http::preventStrayRequests();
        $user = $this->account();
        $resource = $this->resource($user);
        $reference = $this->githubSecret($user, $resource);
        [$token] = $this->grant($user);
        $this->callTool($token, 'git_repository', ['action' => 'commit_file', 'resource_id' => $resource->id, 'reference' => $reference, 'branch' => 'main', 'expected_head_sha' => str_repeat('a', 40), 'path' => $path, 'content' => 'unsafe'])->assertJsonPath('result.content.0.text', 'validation_failed');
        Http::assertNothingSent();
    }

    public function test_grant_command_never_prints_credentials_and_revoke_takes_effect(): void
    {
        $user = $this->account();
        $directory = storage_path('framework/testing/mcp-'.Str::uuid());
        mkdir($directory, 0700);
        $path = $directory.'/client.json';
        $this->artisan('panel:mcp-grant', ['email' => $user->email, 'output' => $path])->doesntExpectOutputToContain('qpm_')->assertSuccessful();
        try {
            $data = json_decode(file_get_contents($path), true);
            $this->assertSame('http://127.0.0.1:8080/mcp/panel', $data['endpoint']);
            $this->assertDatabaseHas('mcp_grants', ['id' => $data['grant_id'], 'token_hash' => hash('sha256', $data['token'])]);
            $this->rpc($data['token'], 'tools/list')->assertOk();
            $this->artisan('panel:mcp-revoke', ['grant' => $data['grant_id']])->assertSuccessful();
            $this->rpc($data['token'], 'tools/list')->assertUnauthorized();
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_private_file_writer_refuses_output_streams(): void
    {
        $this->expectException(\RuntimeException::class);
        PrivateFile::write('php://stdout', 'must-never-be-printed');
    }

    public function test_project_restricted_grants_cannot_create_new_resources(): void
    {
        $user = $this->account();
        $resource = $this->resource($user);
        [$token] = $this->grant($user, ['panel:write'], [$resource->id]);
        $this->callTool($token, 'panel_configure', ['action' => 'create', 'kind' => 'sites', 'name' => 'outside-grant', 'engine' => 'Laravel', 'disk_mb' => 512])->assertJsonPath('result.content.0.text', 'forbidden');
        $this->assertDatabaseCount('resources', 1);
    }

    public function test_version_restore_refuses_changed_secret_bindings(): void
    {
        $user = $this->account();
        $resource = $this->resource($user);
        [$token] = $this->grant($user);
        $secret = $this->toolResult($this->callTool($token, 'vault_manage', ['action' => 'generate', 'resource_id' => $resource->id]));
        $this->toolResult($this->callTool($token, 'vault_manage', ['action' => 'bind', 'resource_id' => $resource->id, 'reference' => $secret['reference'], 'variable' => 'DB_PASSWORD']));
        $version = $this->toolResult($this->callTool($token, 'project_versions', ['action' => 'snapshot', 'resource_id' => $resource->id, 'expected_revision' => 2]));
        $this->toolResult($this->callTool($token, 'vault_manage', ['action' => 'rotate', 'resource_id' => $resource->id, 'reference' => $secret['reference']]));
        $this->callTool($token, 'project_versions', ['action' => 'restore', 'resource_id' => $resource->id, 'expected_revision' => 3, 'version_id' => $version['version_id']])->assertJsonPath('result.content.0.text', 'revision_or_state_conflict');
    }

    public function test_github_commit_uses_pinned_parent_and_non_forced_reference_update(): void
    {
        Http::preventStrayRequests();
        $user = $this->account();
        $resource = $this->resource($user);
        $reference = $this->githubSecret($user, $resource);
        [$token] = $this->grant($user);
        $parent = str_repeat('a', 40);
        $new = str_repeat('b', 40);
        Http::fake([
            'https://api.github.com/repos/example/app/git/refs/heads/main' => Http::sequence()->push(['object' => ['sha' => $parent]])->push(['object' => ['sha' => $new]]),
            'https://api.github.com/repos/example/app/git/commits/'.$parent => Http::response(['tree' => ['sha' => str_repeat('c', 40)]]),
            'https://api.github.com/repos/example/app/git/trees' => Http::response(['sha' => str_repeat('d', 40)]),
            'https://api.github.com/repos/example/app/git/commits' => Http::response(['sha' => $new]),
        ]);
        $result = $this->toolResult($this->callTool($token, 'git_repository', ['action' => 'commit_file', 'resource_id' => $resource->id, 'reference' => $reference, 'branch' => 'main', 'expected_head_sha' => $parent, 'path' => 'src/app.js', 'content' => 'export default 42;']));
        $this->assertSame($new, $result['sha']);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['force'] === false && $request['sha'] === $new);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/git/commits') && $request['parents'] === [$parent]);
    }

    public function test_git_write_requires_explicit_grant(): void
    {
        Http::preventStrayRequests();
        $user = $this->account();
        $resource = $this->resource($user);
        $reference = $this->githubSecret($user, $resource);
        [$token] = $this->grant($user, ['git:read']);
        $this->callTool($token, 'git_repository', ['action' => 'create_branch', 'resource_id' => $resource->id, 'reference' => $reference, 'name' => 'new-branch', 'source_sha' => str_repeat('a', 40)])->assertJsonPath('result.content.0.text', 'forbidden');
        Http::assertNothingSent();
    }

    public function test_public_form_rejects_credentials_embedded_in_repository_urls(): void
    {
        $this->actingAs($this->account())->post('/resources', ['kind' => 'sites', 'name' => 'safe', 'engine' => 'Laravel', 'disk_mb' => 512, 'repository' => 'https://user:password@github.com/example/app'])->assertSessionHasErrors('repository');
        $this->assertDatabaseCount('resources', 0);
    }

    public function test_new_source_commits_can_be_versioned_without_configuration_changes(): void
    {
        $user = $this->account();
        $resource = $this->resource($user);
        [$token] = $this->grant($user);
        $first = $this->toolResult($this->callTool($token, 'project_versions', ['action' => 'snapshot', 'resource_id' => $resource->id, 'expected_revision' => 1, 'source_sha' => str_repeat('a', 40)]));
        $second = $this->toolResult($this->callTool($token, 'project_versions', ['action' => 'snapshot', 'resource_id' => $resource->id, 'expected_revision' => 1, 'source_sha' => str_repeat('b', 40)]));
        $this->assertSame(1, $first['revision']);
        $this->assertSame(2, $second['revision']);
        $this->assertSame(1, $second['resource_revision']);
        $this->assertDatabaseCount('project_versions', 2);
    }

    public function test_github_tree_returns_metadata_without_blob_contents(): void
    {
        Http::preventStrayRequests();
        $user = $this->account();
        $resource = $this->resource($user);
        $reference = $this->githubSecret($user, $resource);
        [$token] = $this->grant($user, ['git:read']);
        $sha = str_repeat('a', 40);
        Http::fake(['https://api.github.com/repos/example/app/git/trees/'.$sha.'*' => Http::response(['tree' => [['path' => 'src/app.js', 'type' => 'blob', 'sha' => str_repeat('b', 40), 'content' => 'must-not-be-returned']], 'truncated' => false])]);
        $response = $this->callTool($token, 'git_repository', ['action' => 'tree', 'resource_id' => $resource->id, 'reference' => $reference, 'source_sha' => $sha]);
        $result = $this->toolResult($response);
        $this->assertSame('src/app.js', $result['entries'][0]['path']);
        $response->assertDontSee('must-not-be-returned', false);
        Http::assertSentCount(1);
    }
}
