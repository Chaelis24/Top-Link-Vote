<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'election_cycle_id',
        'student_department',
        'name',
        'slug',
        'description',
        'max_candidates',
        'max_winners',
        'priority',
        'is_active',
    ];

    public function electionCycle(): BelongsTo
    {
        return $this->belongsTo(ElectionCycle::class, 'election_cycle_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
