<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Key-value store for application-wide settings (e.g. maintenance
 * mode toggle). Values are cast to boolean by default.
 */
class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'boolean',
    ];

    /**
     * Check whether the application is currently in maintenance mode.
     * The result is cached for one hour to reduce database queries.
     *
     * @return bool
     */
    public static function isMaintenanceMode()
    {
        return cache()->remember('maintenanceMode', 3600, function () {
            return (bool) Setting::where('key', 'maintenanceMode')->value('value');
        });
    }
}
