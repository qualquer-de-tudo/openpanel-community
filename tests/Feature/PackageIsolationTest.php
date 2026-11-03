<?php

namespace Tests\Feature;

use Illuminate\Foundation\Auth\User as HostUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\ResponseFactory;
use Inertia\Testing\AssertableInertia;
use QOpenPanel\Community\Models\User;
use Tests\TestCase;

class PackageIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_routes_and_users_do_not_take_over_panel_setup(): void
    {
        Route::middleware('web')->get('/', fn () => response('Host application'));
        Route::middleware('web')->get('/login', fn () => response('Host login'))->name('login');
        DB::table('users')->insert(['name' => 'Host', 'email' => 'host@example.com', 'password' => bcrypt('host-password-123')]);
        $this->get('/')->assertOk()->assertSee('Host application');
        $this->get('/login')->assertOk()->assertSee('Host login');
        $this->get('/qopenpanel')->assertRedirect('/qopenpanel/login');
        $this->get('/qopenpanel/login')->assertInertia(fn (AssertableInertia $p) => $p->where('setup', true));
        $this->post('/qopenpanel/setup', ['name' => 'Panel', 'email' => 'panel@example.com', 'password' => 'panel-password-123', 'password_confirmation' => 'panel-password-123'])->assertRedirect('/qopenpanel');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('qopenpanel_users', ['email' => 'panel@example.com']);
    }

    public function test_host_login_does_not_authorize_panel(): void
    {
        $host = new HostUser;
        $host->forceFill(['id' => 1, 'name' => 'Host', 'email' => 'host@example.com']);
        $this->actingAs($host, 'web')->get('/qopenpanel')->assertRedirect('/qopenpanel/login');
        $this->assertAuthenticatedAs($host, 'web');
    }

    public function test_panel_logout_preserves_host_session(): void
    {
        $host = new HostUser;
        $host->forceFill(['id' => 99, 'name' => 'Host', 'email' => 'host@example.com']);
        $panel = User::factory()->create();
        $this->actingAs($host, 'web')->actingAs($panel, 'qopenpanel')->withSession(['host_preference' => 'keep'])->post('/qopenpanel/logout')->assertRedirect('/qopenpanel/login')->assertSessionHas('host_preference', 'keep');
        $this->assertAuthenticatedAs($host, 'web');
        $this->assertGuest('qopenpanel');
    }

    public function test_package_restores_host_translator_validator_and_inertia(): void
    {
        $translator = app('translator');
        $validator = app('validator');
        $inertia = app(ResponseFactory::class);
        $locale = app()->getLocale();
        config(['catalog' => ['host' => 'kept']]);
        $this->withHeader('Accept-Language', 'ja')->get('/qopenpanel/login')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('locale', 'ja'));
        $this->assertSame($translator, app('translator'));
        $this->assertSame($validator, app('validator'));
        $this->assertSame($inertia, app(ResponseFactory::class));
        $this->assertSame($locale, app()->getLocale());
        $this->assertSame(['host' => 'kept'], config('catalog'));
    }

    public function test_legacy_import_requires_confirmation_and_preserves_original_data(): void
    {
        $tables = ['users', 'accounts', 'resources', 'mcp_grants', 'vault_secrets', 'secret_bindings', 'project_versions', 'mcp_audit', 'deployment_plans', 'template_installations', 'template_installation_history'];
        foreach (array_slice($tables, 1) as $table) {
            $sql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'qopenpanel_'.$table)->value('sql');
            DB::statement(str_replace('qopenpanel_', '', $sql));
        }
        foreach (['2026_11_03_074212_create_resources_table', '2026_11_03_093803_create_mcp_security_tables', '2026_11_03_103013_create_template_installations_tables'] as $migration) {
            DB::table('migrations')->insert(['migration' => $migration, 'batch' => 99]);
        }
        DB::table('users')->insert(['id' => 17, 'name' => 'Legacy', 'email' => 'legacy@example.com', 'password' => bcrypt('legacy-password-123')]);
        DB::table('accounts')->insert(['user_id' => 17, 'disk_mb' => 2048, 'memory_mb' => 1024, 'project_limit' => 3, 'database_limit' => 2]);
        $this->artisan('qopenpanel:import-legacy')->assertFailed();
        $this->assertDatabaseCount('qopenpanel_users', 0);
        $this->artisan('qopenpanel:import-legacy', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseHas('qopenpanel_users', ['id' => 17, 'email' => 'legacy@example.com']);
        $this->assertDatabaseHas('qopenpanel_accounts', ['user_id' => 17, 'disk_mb' => 2048]);
        $this->assertDatabaseHas('users', ['id' => 17, 'email' => 'legacy@example.com']);
        $this->artisan('qopenpanel:import-legacy', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('qopenpanel_users', 1);
    }

    public function test_installation_identity_is_validated_and_prefills_first_access(): void
    {
        $path = storage_path('app/qopenpanel-setup.json');
        try {
            $this->artisan('qopenpanel:configure', ['--name' => 'Operator', '--email' => 'invalid'])->assertFailed();
            $this->artisan('qopenpanel:configure', ['--name' => 'Operator', '--email' => 'operator@example.com'])->assertSuccessful();
            $this->get('/qopenpanel/login')->assertInertia(fn (AssertableInertia $p) => $p->where('defaults.name', 'Operator')->where('defaults.email', 'operator@example.com'));
            $this->assertDatabaseCount('qopenpanel_users', 0);
            User::factory()->create();
            $this->get('/qopenpanel/login')->assertInertia(fn (AssertableInertia $p) => $p->where('setup', false)->where('defaults', []));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
