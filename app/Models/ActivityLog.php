<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Records every significant action performed by admin users and students
 * for auditing and security review. Each log entry captures who did what,
 * from which IP/location, and at what time.
 */
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


    /**
     * Create a new activity-log entry with caller context (IP, user agent,
     * campus-vs-external detection) and the authenticated user's ID.
     *
     * @param  string      $action       Short identifier (e.g. 'vote.cast', 'student.created').
     * @param  string      $description  Human-readable summary of the action.
     * @param  array|null  $properties   Optional JSON-serialisable metadata.
     * @param  int|null    $studentId    Related student, if applicable.
     * @return self
     */
    public static function log(string $action, string $description, ?array $properties = null, ?int $studentId = null)
    {
        $ip = request()->ip();

        $isCampus = str_starts_with($ip, '10.0') || str_starts_with($ip, '192.168') || $ip === '127.0.0.1';
        $location = $isCampus ? "Campus Network ($ip)" : "External ($ip)";

        return self::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'student_id'  => $studentId,
            'description' => $description,
            'ip_address'  => $location,
            'user_agent'  => request()->userAgent(),
            'properties'  => $properties,
        ]);
    }


    /**
     * The user who performed the action. Falls back to a placeholder
     * if the original user has been deleted.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'System / Deleted User'
        ]);
    }

    /**
     * The student that the log entry relates to (if any). Falls back
     * to a placeholder if the student record no longer exists.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withDefault([
            'student_id' => 'N/A',
            'first_name' => 'Deleted',
            'last_name' => 'Student'
        ]);
    }
}
