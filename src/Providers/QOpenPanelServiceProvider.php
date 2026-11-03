<?php

namespace QOpenPanel\Community\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use QOpenPanel\Community\Console\Commands\AuthorizeMaintenance;
use QOpenPanel\Community\Console\Commands\ConfigurePanel;
use QOpenPanel\Community\Console\Commands\DesignateOperator;
use QOpenPanel\Community\Console\Commands\ImportLegacyPanel;
use QOpenPanel\Community\Console\Commands\InstallPanel;
use QOpenPanel\Community\Console\Commands\MaintenanceHealth;
use QOpenPanel\Community\Console\Commands\McpGrant;
use QOpenPanel\Community\Console\Commands\McpRevoke;
use QOpenPanel\Community\Console\Commands\TemplatesCheck;
use QOpenPanel\Community\Console\Commands\TemplatesLock;
use QOpenPanel\Community\Console\Commands\VaultImport;
use QOpenPanel\Community\Console\Commands\VaultMaterialize;
use QOpenPanel\Community\Models\User;

class QOpenPanelServiceProvider extends ServiceProvider
{
    public static function path(string $path = ''): string
    {
        return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(static::path('config/qopenpanel.php'), 'qopenpanel');
        config(['auth.guards.qopenpanel' => ['driver' => 'session', 'provider' => 'qopenpanel'], 'auth.providers.qopenpanel' => ['driver' => 'eloquent', 'model' => User::class]]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(static::path('resources/views'), 'qopenpanel');
        $this->loadMigrationsFrom(static::path('database/package-migrations'));
        $this->loadRoutesFrom(static::path('routes/panel.php'));
        $this->loadRoutesFrom(static::path('routes/panel-mcp.php'));
        RateLimiter::for('panel-mcp', fn (Request $request) => Limit::perMinute(60)->by($request->attributes->get('mcp_grant')?->id ?? $request->ip()));
        RateLimiter::for('openpanel-maintenance-read', fn (Request $request) => Limit::perMinute(6)->by(Auth::guard('qopenpanel')->id() ?? $request->ip()));
        RateLimiter::for('openpanel-maintenance-write', fn (Request $request) => Limit::perMinute(2)->by(Auth::guard('qopenpanel')->id() ?? $request->ip()));
        EncryptCookies::except('qopenpanel_locale');
        if ($this->app->runningInConsole()) {
            $this->publishes([static::path('resources/dist') => public_path('vendor/qopenpanel')], 'qopenpanel-assets');
            $this->publishes([static::path('config/qopenpanel.php') => config_path('qopenpanel.php')], 'qopenpanel-config');
            $this->commands([AuthorizeMaintenance::class, DesignateOperator::class, MaintenanceHealth::class, InstallPanel::class, ImportLegacyPanel::class, ConfigurePanel::class, McpGrant::class, McpRevoke::class, VaultImport::class, VaultMaterialize::class, TemplatesCheck::class, TemplatesLock::class]);
            require static::path('routes/panel-console.php');
        }
    }
}
