<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Records a single vote cast for a candidate in a given position
 * within an election cycle. Each vote is linked to a student and
 * assigned a unique reference number for confirmation purposes.
 */
class Vote extends Model
{
    use HasFactory;

    protected $table = 'votes';

    protected $fillable = [
        'student_id',
        'candidate_id',
        'position_id',
        'election_cycle_id',
        'reference_number',
        'voted_at',
    ];

    /**
     * The student who cast this vote.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The candidate who received this vote.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_id', 'id');
    }

    /**
     * The position for which this vote was cast.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * The election cycle in which this vote was cast.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function electionCycle(): BelongsTo
    {
        return $this->belongsTo(ElectionCycle::class);
    }
}
