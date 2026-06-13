<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Provides a reusable `logout` method for Livewire components.
 * Determines the correct redirect route based on the user's role
 * (admin vs. student) and supports both Livewire and standard redirects.
 */
trait AuthenticatesLogout
{
    /**
     * Log out the authenticated user, invalidate the session,
     * regenerate the CSRF token, and redirect to the appropriate
     * login page based on the user's role.
     *
     * @return mixed
     */
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
