<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Platform extends Model
{
    protected $fillable = [
        'candidate_id',
        'title',
        'vision',
        'mission',
        'goals',
        'action_plans',
        'status',
        'admin_notes',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'goals' => 'array',
        'action_plans' => 'array',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
