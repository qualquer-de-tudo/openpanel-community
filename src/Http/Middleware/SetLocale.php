<?php

namespace QOpenPanel\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use QOpenPanel\Community\Providers\QOpenPanelServiceProvider;
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
        $languages = config('qopenpanel.locales.languages');
        $codes = array_column($languages, 'code');
        $cookie = $request->cookie('qopenpanel_locale');
        $locale = is_string($cookie) && in_array($cookie, $codes, true) ? $cookie : null;
        if ($locale === null && $request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage($codes);
            $locale = in_array($preferred, $codes, true) ? $preferred : null;
        }
        $fallback = config('qopenpanel.locales.default', 'pt_BR');
        $locale ??= in_array($fallback, $codes, true) ? $fallback : 'pt_BR';
        $app = app();
        $originalTranslator = $app['translator'];
        $originalValidator = $app['validator'];
        $originalLocale = config('app.locale');
        $loader = new FileLoader($app['files'], QOpenPanelServiceProvider::path('lang'));
        $loader->addJsonPath(QOpenPanelServiceProvider::path('lang'));
        $translator = new Translator($loader, $locale);
        $translator->setFallback('en');
        $factory = new Factory($translator, $app);
        if ($originalValidator->getPresenceVerifier()) {
            $factory->setPresenceVerifier($originalValidator->getPresenceVerifier());
        }
        $app->instance('translator', $translator);
        $app->instance('validator', $factory);
        Lang::clearResolvedInstance('translator');
        Validator::clearResolvedInstance('validator');
        config(['app.locale' => $locale]);
        try {
            return $next($request);
        } finally {
            $app->instance('translator', $originalTranslator);
            $app->instance('validator', $originalValidator);
            Lang::clearResolvedInstance('translator');
            Validator::clearResolvedInstance('validator');
            config(['app.locale' => $originalLocale]);
        }
    }
}
