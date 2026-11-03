<?php

namespace QOpenPanel\Community\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\ResponseFactory;
use QOpenPanel\Community\Providers\QOpenPanelServiceProvider;

class HandleInertiaRequests extends Middleware
{
    public function handle(Request $request, \Closure $next)
    {
        $original = app(ResponseFactory::class);
        app()->instance(ResponseFactory::class, new ResponseFactory);
        Inertia::clearResolvedInstance(ResponseFactory::class);
        try {
            return parent::handle($request, $next);
        } finally {
            app()->instance(ResponseFactory::class, $original);
            Inertia::clearResolvedInstance(ResponseFactory::class);
        }
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'qopenpanel::app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return hash_file('sha256', QOpenPanelServiceProvider::path('resources/dist/manifest.json'));
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
        ];
    }
}
