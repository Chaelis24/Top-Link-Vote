<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A position (e.g. "President", "Vice President") that candidates
 * can run for within an election cycle. Defines the maximum number
 * of candidates allowed and how many winners are elected.
 */
class Position extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'election_cycle_id',
        'student_department',
        'name',
        'max_candidates',
        'max_winners',
        'priority',
        'is_active',
    ];

    /**
     * The election cycle this position belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function electionCycle(): BelongsTo
    {
        return $this->belongsTo(ElectionCycle::class);
    }

    /**
     * All candidates running for this position.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * All votes cast for candidates in this position.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
