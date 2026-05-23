<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            return $user->role === 'admin';
        });

        Gate::define('student', function (User $user) {
            return $user->role === 'student';
        });
    }
}
