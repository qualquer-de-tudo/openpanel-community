<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPanelPathTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('qopenpanel.path', 'infra');
    }

    public function test_custom_path_preserves_host_routes_and_generated_panel_links(): void
    {
        $this->get('/infra/login')->assertOk()->assertSee('data-qopenpanel-path="/infra"', false);
        $this->get('/infra')->assertRedirect('/infra/login');
        $this->get('/openpanel/login')->assertNotFound();
        $this->assertSame('/infra', route('qopenpanel.home', [], false));
    }
}
