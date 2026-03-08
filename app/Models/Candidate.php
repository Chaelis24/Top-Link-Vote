<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Candidate extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'position_id',
        'election_cycle_id',
        'party_name',
        'slogan',
        'course',
        'photo',
        'bio',
        'achievements',
        'status',
        'approved_at',
        'votes_count'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForStudentCourse($query, $course)
    {
        return $query->where('course', $course);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function platform(): HasOne
    {
        return $this->hasOne(Platform::class);
    }

    public function electionCycle(): BelongsTo
    {
        return $this->belongsTo(ElectionCycle::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
