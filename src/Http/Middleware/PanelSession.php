<?php

namespace QOpenPanel\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $original = $request->getUserResolver();
        $request->setUserResolver(fn () => Auth::guard('qopenpanel')->user());
        try {
            return $next($request);
        } finally {
            $request->setUserResolver($original);
        }
    }
}
