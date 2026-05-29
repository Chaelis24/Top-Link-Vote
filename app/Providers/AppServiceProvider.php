<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Request $request): void
    {
        if ($request->header('X-Socket-ID') === 'undefined') {
            $request->headers->set('X-Socket-ID', null);
        }

        Gate::define('admin', function (User $user) {
            return $user->hasRole('admin');
        });

        Gate::define('student', function (User $user) {
            return $user->hasRole('student');
        });

        View::composer('*', function ($view) {
            $isMaintenance = Cache::remember('maintenanceMode', 3600, function () {
                return (bool) Setting::where('key', 'maintenanceMode')->value('value');
            });

            $view->with('isMaintenance', $isMaintenance);
        });
    }
}
