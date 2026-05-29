<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $isMaintenance = Cache::remember('maintenance_mode', 60, function () {
            return (bool) Setting::where('key', 'maintenanceMode')->value('value');
        });

        if ($isMaintenance && !Auth::check()) {
            return response()->view('maintenance');
        }

        return $next($request);
    }
}
