<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('invite.supported_locales', ['en', 'am']);

        $locale = null;

        if ($request->user() && in_array($request->user()->locale, $supported, true)) {
            $locale = $request->user()->locale;
        } elseif ($request->hasCookie('locale') && in_array($request->cookie('locale'), $supported, true)) {
            $locale = $request->cookie('locale');
        } elseif ($request->session()->has('locale') && in_array($request->session()->get('locale'), $supported, true)) {
            $locale = $request->session()->get('locale');
        }

        if (! $locale) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
