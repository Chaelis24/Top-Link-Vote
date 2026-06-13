<?php

namespace App\Traits;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Provides a reusable maintenance-mode check for Livewire components.
 * When maintenance is active, non-admin users are logged out and
 * redirected to the home page with a status message.
 */
trait ChecksMaintenance
{
    /**
     * Check whether the application is in maintenance mode. If it is
     * and the current user is not an admin, force a logout and redirect.
     *
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function checkMaintenance()
    {
        $isMaintenance = (bool) Cache::remember('maintenanceMode', 3600, function () {
            return Setting::where('key', 'maintenanceMode')->value('value') ?? false;
        });

        if ($isMaintenance && !auth()->guard('admin')->check()) {

            if (Auth::check()) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
            }

            return redirect('/')->with('message', 'System is under maintenance.');
        }
    }
}
