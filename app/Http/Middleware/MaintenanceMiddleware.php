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
    protected array $except = [
        '/',
        'admin-login',
        'admin-forgot-password',
        'admin-reset-password/*',
        'verify-account',
        'forgot-password',
        'reset-password/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        $isMaintenance = Cache::remember('maintenanceMode', 60, function () {
            return (bool) Setting::where('key', 'maintenanceMode')->value('value');
        });

        if ($isMaintenance) {
            if (!Auth::check()) {
                return response()->view('maintenance');
            }
            if (!Auth::user()->hasRole('admin')) {
                Auth::logout();
                return response()->view('maintenance');
            }
        }

        return $next($request);
    }
}
