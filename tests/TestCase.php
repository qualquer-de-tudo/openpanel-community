<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Inertia\ServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use QOpenPanel\Community\Providers\QOpenPanelServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class, McpServiceProvider::class, QOpenPanelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('session.driver', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? 'qopenpanel');
    }
}
