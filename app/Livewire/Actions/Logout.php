<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Action class for logging out the currently authenticated user.
 *
 * Invalidates the session and regenerates the CSRF token
 * to prevent session fixation attacks after logout.
 */
class Logout
{
    /**
     * Log the current user out of the application.
     *
     * Flushes the session data and regenerates the session token
     * to ensure a clean post-logout state.
     *
     * @return void
     */
    public function __invoke(): void
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
