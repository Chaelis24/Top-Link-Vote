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

        if (method_exists($this, 'redirect')) {
            return $this->redirect(route($route), navigate: false);
        }

        return redirect(route($route));
    }
}
