<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app is Slovak or English depending on the phone, so messages it shows
 * straight from the API (failed login, …) follow its Accept-Language.
 */
class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->getPreferredLanguage(['sk', 'en']) ?? 'en');

        return $next($request);
    }
}
