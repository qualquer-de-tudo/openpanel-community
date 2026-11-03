<?php

use Illuminate\Foundation\Application;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(fn ($middleware) => null)
    ->withExceptions(fn ($exceptions) => null)
    ->create();
$app->useAppPath(dirname(__DIR__).'/src');

return $app;
