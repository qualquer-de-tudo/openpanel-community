<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthenticatePanelMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $this->authenticate($request, $next);
        } catch (TooManyRequestsHttpException) {
            return response()->json(['error' => 'rate_limit_exceeded'], 429)->header('Cache-Control', 'no-store');
        } catch (\Throwable) {
            return response()->json(['error' => 'mcp_unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }

    private function authenticate(Request $request, Closure $next): Response
    {
        if (strlen($request->getContent()) > 262144) {
            return response()->json(['error' => 'request_too_large'], 413);
        }
        if ($request->header('Origin') !== null) {
            return response()->json(['error' => 'browser_origins_not_allowed'], 403);
        }
        $token = $request->bearerToken();
        if (! $token || ! preg_match('/^qpm_[a-f0-9]{96}$/D', $token)) {
            return response()->json(['error' => 'invalid_mcp_access'], 401);
        }
        $grant = DB::table('mcp_grants')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if (! $grant) {
            return response()->json(['error' => 'invalid_mcp_access'], 401);
        }
        if (! $request->isSecure() && ! in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true)) {
            return response()->json(['error' => 'https_required'], 403);
        }
        $user = User::find($grant->user_id);
        if (! $user) {
            return response()->json(['error' => 'invalid_mcp_access'], 401);
        }
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mcp_grant', $grant);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
