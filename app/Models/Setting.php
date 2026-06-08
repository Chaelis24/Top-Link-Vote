<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'boolean',
    ];

    public static function isMaintenanceMode()
    {
        return cache()->remember('maintenanceMode', 3600, function () {
            return (bool) Setting::where('key', 'maintenanceMode')->value('value');
        });
    }
}
