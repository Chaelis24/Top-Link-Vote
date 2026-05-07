<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
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

    public static function log($action, $description, $properties = null, $studentId = null)
    {
        self::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'student_id' => $studentId,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => $properties,
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
