<?php

namespace App\Traits;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

trait ChecksMaintenance
{
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
