<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $languages = config('locales.languages');
        $codes = array_column($languages, 'code');
        $cookie = $request->cookie('qopenpanel_locale');
        $locale = is_string($cookie) && in_array($cookie, $codes, true) ? $cookie : null;
        if ($locale === null && $request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage($codes);
            $locale = in_array($preferred, $codes, true) ? $preferred : null;
        }
        $fallback = config('locales.default', 'pt_BR');
        app()->setLocale($locale ?? (in_array($fallback, $codes, true) ? $fallback : 'pt_BR'));

        return $next($request);
    }
}
