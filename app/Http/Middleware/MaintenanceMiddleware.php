<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that blocks non-admin traffic when the application is
 * in maintenance mode. Routes listed in `$except` are always allowed
 * (e.g. login, password-reset). Authenticated students/candidates are
 * logged out and redirected to the home page with a notification.
 */
class MaintenanceMiddleware
{
    /**
     * URI patterns that bypass the maintenance check.
     *
     * @var array<int, string>
     */
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
            $user = Auth::user();

            if ($user && $user->hasAnyRole(['student', 'candidate'])) {

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->hasHeader('X-Livewire')) {
                    return response()->json(['redirect' => url('/')], 200)
                        ->header('X-Livewire-Redirect', url('/'));
                }

                return redirect('/')->with('status', 'The system is currently under maintenance.');
            }
        }

        return $next($request);
    }
}
