<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait AuthenticatesLogout
{
    public function logout()
    {
        $user = Auth::user();
        $route = ($user && $user->hasRole('admin')) ? 'admin.login' : 'login';
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect(route($route));
    }
}
