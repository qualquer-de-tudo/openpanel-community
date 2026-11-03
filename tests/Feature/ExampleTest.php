<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOpenPanel\Community\Models\Resource;
use QOpenPanel\Community\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        $u = User::factory()->create();
        DB::table('qopenpanel_accounts')->insert(['user_id' => $u->id, 'disk_mb' => 1024, 'memory_mb' => 512, 'project_limit' => 1, 'database_limit' => 1]);

        return $u;
    }

    private function payload(array $extra = []): array
    {
        return [...['kind' => 'sites', 'name' => 'meu-site', 'engine' => 'Laravel', 'disk_mb' => 512], ...$extra];
    }

    public function test_guests_must_authenticate(): void
    {
        $this->get('/openpanel')->assertRedirect('/openpanel/login');
        $this->post('/openpanel/resources', $this->payload())->assertRedirect('/openpanel/login');
    }

    public function test_first_install_creates_account_and_closes_setup(): void
    {
        $this->post('/openpanel/setup', ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123'])->assertRedirect('/openpanel');
        $this->assertDatabaseHas('qopenpanel_accounts', ['user_id' => 1]);
        $this->post('/openpanel/setup', ['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123'])->assertForbidden();
    }

    public function test_dashboard_and_login_render(): void
    {
        $this->get('/openpanel/login')->assertOk();
        $this->actingAs($this->account())->get('/openpanel')->assertOk();
    }

    public function test_resource_is_persisted_as_planned(): void
    {
        $u = $this->account();
        $this->actingAs($u)->post('/openpanel/resources', $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qopenpanel_resources', ['user_id' => $u->id, 'status' => 'planned', 'name' => 'meu-site']);
    }

    public function test_disk_and_project_limits_are_enforced(): void
    {
        $this->actingAs($this->account())->post('/openpanel/resources', $this->payload(['disk_mb' => 2048]))->assertSessionHasErrors('disk_mb');
        $this->post('/openpanel/resources', $this->payload())->assertSessionHasNoErrors();
        $this->post('/openpanel/resources', $this->payload(['name' => 'second']))->assertSessionHasErrors('name');
    }

    public function test_invalid_engine_domain_and_repository_are_rejected(): void
    {
        $this->actingAs($this->account())->post('/openpanel/resources', $this->payload(['engine' => 'shell-command', 'domain' => 'bad;rm', 'repository' => 'http://insecure.test/repo']))->assertSessionHasErrors(['engine', 'domain', 'repository']);
    }

    public function test_other_accounts_cannot_read_or_delete_resources(): void
    {
        $a = $this->account();
        $b = $this->account();
        $r = Resource::create([...$this->payload(), 'user_id' => $a->id]);
        $this->actingAs($b)->get('/openpanel')->assertDontSee('meu-site');
        $this->delete('/openpanel/resources/'.$r->id)->assertNotFound();
        $this->assertDatabaseCount('qopenpanel_resources', 1);
    }

    public function test_owner_can_remove_configuration(): void
    {
        $u = $this->account();
        $r = Resource::create([...$this->payload(), 'user_id' => $u->id]);
        $this->actingAs($u)->delete('/openpanel/resources/'.$r->id)->assertRedirect();
        $this->assertDatabaseCount('qopenpanel_resources', 0);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->account();
        $this->post('/openpanel/login', ['email' => 'wrong@example.com', 'password' => 'incorrect'])->assertSessionHasErrors('email');
    }

    public function test_non_scalar_category_returns_validation_error(): void
    {
        $this->actingAs($this->account())->post('/openpanel/resources', $this->payload(['kind' => ['sites']]))->assertSessionHasErrors('kind');
    }
}
