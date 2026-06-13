<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A candidate's platform statement for a specific election cycle.
 * Contains a title, tagline, and a JSON agenda array of plan items.
 */
class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'title',
        'tagline',
        'agenda',
        'status',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'agenda' => 'array',
    ];

    /**
     * The candidate who owns this platform.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
