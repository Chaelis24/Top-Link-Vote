<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeSocketIdHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Socket-ID') === 'undefined') {
            $request->headers->set('X-Socket-ID', null);

            $request->server->set('HTTP_X_SOCKET_ID', null);
        }

        return $next($request);
    }
}
