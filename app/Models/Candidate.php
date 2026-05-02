<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_id',
        'position_id',
        'election_cycle_id',
        'party_name',
        'achievements',
        'photo',
        'previous_position',
        'previous_school_project',
        'average_grade',
        'status',
        'approved_at',
        'votes_count'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'average_grade' => 'decimal:2',
        'votes_count' => 'integer',
        'previous_position' => 'array',
        'previous_school_project' => 'array',
        'achievements' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
