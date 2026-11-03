<?php

namespace QOpenPanel\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePanelUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $request->expectsJson() ? response()->json(['message' => 'Unauthenticated.'], 401) : redirect()->route('qopenpanel.login');
        }

        return $next($request);
    }
}
