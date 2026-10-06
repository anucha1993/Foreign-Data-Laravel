<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');
        $locale = Locales::has($requested) ? $requested : $request->cookie('lang');
        if (! Locales::has($locale)) {
            $locale = 'th';
        }

        App::setLocale($locale);

        $response = $next($request);

        if ($requested === $locale && $requested !== $request->cookie('lang')) {
            $response->headers->setCookie(Cookie::make('lang', $locale, 60 * 24 * 365));
        }

        return $response;
    }
}
