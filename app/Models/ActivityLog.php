<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'student_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'properties'
    ];

    protected $casts = [
        'properties' => 'json',
        'created_at' => 'datetime',
    ];


    public static function log($action, $description, $oldValue = null, $newValue = null, $studentId = null)
    {
        $ip = request()->ip();
        $isCampus = str_starts_with($ip, '10.0') || str_starts_with($ip, '192.168') || $ip === '127.0.0.1';
        $location = $isCampus ? "Campus Network ($ip)" : "External ($ip)";

        return self::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'student_id' => $studentId,
            'description' => $description,
            'ip_address' => $location,
            'user_agent' => request()->userAgent(),
        ]);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'first_name' => 'System /',
            'last_name' => 'Deleted User'
        ]);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withDefault([
            'id' => 'N/A'
        ]);
    }
}
