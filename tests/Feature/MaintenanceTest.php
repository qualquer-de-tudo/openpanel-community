<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use QOpenPanel\Community\Models\User;
use QOpenPanel\Community\Services\MaintenanceBridge;
use QOpenPanel\Community\Services\OfficialReleases;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use DatabaseMigrations;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/maintenance-'.Str::uuid());
        mkdir($this->directory.'/requests', 0700, true);
        mkdir($this->directory.'/reports', 0700);
        file_put_contents($this->directory.'/agent.json', json_encode(['timestamp' => time(), 'version' => 'v0.7.0-beta.1']));
        config(['qopenpanel.maintenance_path' => $this->directory]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach (['requests', 'reports', ''] as $directory) {
            foreach (glob($this->directory.'/'.$directory.'/*') as $file) {
                if (is_file($file) || is_link($file)) {
                    unlink($file);
                }
            }
            if ($directory !== '') {
                rmdir($this->directory.'/'.$directory);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function operator(): User
    {
        return User::factory()->create(['is_operator' => true]);
    }

    private function updatePayload(array $extra = []): array
    {
        return [...['action' => 'update', 'version' => 'v0.8.0-beta.1', 'channel' => 'beta', 'password' => 'password', 'acknowledge' => true], ...$extra];
    }

    private function releases(): void
    {
        Http::fake([OfficialReleases::API.'/releases*' => Http::response([['tag_name' => 'v0.8.0-beta.1', 'draft' => false, 'prerelease' => true, 'html_url' => 'https://untrusted.example'], ['tag_name' => 'v0.8.0', 'draft' => false, 'prerelease' => false], ['tag_name' => 'v0.9.0', 'draft' => true, 'prerelease' => false], ['tag_name' => 'v1.0.0;id', 'draft' => false, 'prerelease' => false]]), OfficialReleases::API.'/commits/*' => Http::response(['sha' => str_repeat('a', 40)])]);
    }

    public function test_host_maintenance_is_not_available_to_guests_or_regular_tenants(): void
    {
        $this->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertRedirect('/qopenpanel/login');
        $this->actingAs(User::factory()->create())->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertForbidden();
        $this->getJson('/qopenpanel/maintenance/releases?channel=beta')->assertForbidden();
        $this->assertDatabaseCount('qopenpanel_maintenance_jobs', 0);
        Http::assertNothingSent();
    }

    public function test_operator_role_is_local_and_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com', 'password' => 'password', 'is_operator' => true]);
        $this->assertFalse($user->fresh()->is_operator);
        $this->artisan('qopenpanel:operator', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_operator);
        $this->artisan('qopenpanel:operator', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_operator);
    }

    public function test_release_channels_exclude_drafts_unstable_tags_and_untrusted_urls(): void
    {
        $this->releases();
        $this->actingAs($this->operator())->getJson('/qopenpanel/maintenance/releases?channel=stable')->assertOk()->assertJsonCount(1, 'releases')->assertJsonPath('releases.0.tag', 'v0.8.0');
        $this->getJson('/qopenpanel/maintenance/releases?channel=beta')->assertOk()->assertJsonCount(2, 'releases')->assertJsonPath('releases.0.url', 'https://github.com/qualquer-de-tudo/openpanel-community/releases/tag/v0.8.0-beta.1');
    }

    public function test_scan_writes_only_a_bounded_protocol_job_without_credentials(): void
    {
        $this->actingAs($this->operator())->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertRedirect();
        $job = DB::table('qopenpanel_maintenance_jobs')->first();
        $payload = json_decode(file_get_contents($this->directory.'/requests/'.$job->id.'.json'), true);
        $this->assertSame('scan', $payload['action']);
        $this->assertNull($payload['version']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('command', $payload);
        Http::assertNothingSent();
    }

    public function test_update_requires_reauthentication_and_explicit_acknowledgement(): void
    {
        $this->actingAs($this->operator())->post('/qopenpanel/maintenance/request', $this->updatePayload(['password' => 'incorrect']))->assertSessionHasErrors('password');
        $this->post('/qopenpanel/maintenance/request', $this->updatePayload(['acknowledge' => false]))->assertSessionHasErrors('acknowledge');
        $this->assertDatabaseCount('qopenpanel_maintenance_jobs', 0);
        Http::assertNothingSent();
    }

    public function test_valid_update_is_pinned_to_a_commit_and_never_queues_the_password(): void
    {
        $this->releases();
        $this->actingAs($this->operator())->post('/qopenpanel/maintenance/request', $this->updatePayload())->assertRedirect();
        $job = DB::table('qopenpanel_maintenance_jobs')->first();
        $text = file_get_contents($this->directory.'/requests/'.$job->id.'.json');
        $this->assertSame(str_repeat('a', 40), json_decode($text, true)['sha']);
        $this->assertStringNotContainsString('password', $text);
        $this->assertStringNotContainsString('acknowledge', $text);
    }

    public function test_stale_agent_blocks_jobs_without_claiming_success(): void
    {
        file_put_contents($this->directory.'/agent.json', json_encode(['timestamp' => time() - 3600]));
        $this->actingAs($this->operator())->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('qopenpanel_maintenance_jobs', 0);
    }

    public function test_server_reports_are_not_shared_with_regular_tenants(): void
    {
        $user = User::factory()->create();
        DB::table('qopenpanel_accounts')->insert(['user_id' => $user->id]);
        $this->actingAs($user)->get('/qopenpanel')->assertInertia(fn (AssertableInertia $page) => $page->where('maintenance', null));
    }

    public function test_release_reads_do_not_consume_the_write_rate_limit(): void
    {
        $this->releases();
        $this->actingAs($this->operator());
        for ($index = 0; $index < 6; $index++) {
            $this->getJson('/qopenpanel/maintenance/releases?channel=stable')->assertOk();
        }
        $this->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('qopenpanel_maintenance_jobs', 1);
    }

    public function test_free_form_commands_are_refused_and_never_dispatched(): void
    {
        $this->actingAs($this->operator())->post('/qopenpanel/maintenance/request', ['action' => 'scan', 'command' => 'arbitrary'])->assertUnprocessable();
        $this->assertDatabaseCount('qopenpanel_maintenance_jobs', 0);
    }

    public function test_mcp_context_cannot_call_the_bridge_even_as_an_operator(): void
    {
        $user = $this->operator();
        $this->actingAs($user);
        request()->attributes->set('mcp_grant', (object) ['id' => 'restricted']);
        try {
            app(MaintenanceBridge::class)->request($user->id, 'scan');
            $this->fail('MCP must not dispatch host maintenance.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertDatabaseCount('qopenpanel_maintenance_jobs', 0);
    }

    public function test_permission_revocation_invalidates_pending_agent_authorization(): void
    {
        $user = $this->operator();
        $this->actingAs($user)->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertRedirect();
        $job = DB::table('qopenpanel_maintenance_jobs')->first();
        $arguments = ['id' => $job->id, 'action' => 'scan', 'target' => '-', 'sha' => '-'];
        $this->artisan('qopenpanel:maintenance-authorize', $arguments)->assertSuccessful();
        $user->forceFill(['is_operator' => false])->save();
        $this->artisan('qopenpanel:maintenance-authorize', $arguments)->assertFailed();
    }

    public function test_failed_file_dispatch_is_recorded_and_does_not_claim_a_queued_task(): void
    {
        rmdir($this->directory.'/requests');
        try {
            $this->actingAs($this->operator())->post('/qopenpanel/maintenance/request', ['action' => 'scan'])->assertSessionHasErrors('action');
            $this->assertDatabaseHas('qopenpanel_maintenance_jobs', ['dispatch_failed' => true]);
        } finally {
            mkdir($this->directory.'/requests', 0700);
        }
    }

    public function test_health_probe_requires_the_expected_version_and_a_decryptable_existing_vault(): void
    {
        $marker = $this->directory.'/ready';
        config(['qopenpanel.maintenance_ready_path' => $marker]);
        $version = config('qopenpanel.release.version');
        $this->artisan('qopenpanel:maintenance-health', ['version' => $version])->assertFailed();
        touch($marker);
        $user = $this->operator();
        $id = (string) Str::uuid();
        DB::table('qopenpanel_vault_secrets')->insert(['id' => $id, 'user_id' => $user->id, 'resource_id' => 1, 'purpose' => 'application', 'ciphertext' => Crypt::encryptString('test-only-value'), 'version' => 1]);
        $this->artisan('qopenpanel:maintenance-health', ['version' => $version])->assertSuccessful();
        $job = (string) Str::uuid();
        DB::table('qopenpanel_maintenance_jobs')->insert(['id' => $job, 'user_id' => $user->id, 'action' => 'update', 'target' => 'v'.$version, 'expected_sha' => str_repeat('a', 40), 'created_at' => now(), 'updated_at' => now()]);
        $probe = ['version' => $version, '--job' => $job, '--sha' => str_repeat('a', 40)];
        $this->artisan('qopenpanel:maintenance-health', $probe)->assertSuccessful();
        $this->artisan('qopenpanel:maintenance-health', array_replace($probe, ['--sha' => str_repeat('b', 40)]))->assertFailed();
        $user->forceFill(['is_operator' => false])->save();
        $this->artisan('qopenpanel:maintenance-health', $probe)->assertFailed();
        $this->artisan('qopenpanel:maintenance-health', ['version' => '99.0.0'])->assertFailed();
        DB::table('qopenpanel_vault_secrets')->where('id', $id)->update(['ciphertext' => 'invalid']);
        $this->artisan('qopenpanel:maintenance-health', ['version' => $version])->assertFailed();
    }
}
