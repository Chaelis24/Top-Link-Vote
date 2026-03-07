<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionCycle extends Model
{
    protected $fillable = [
        'name',
        'academic_year',
        'semester',
        'filing_start',
        'filing_end',
        'campaign_start',
        'campaign_end',
        'voting_start',
        'voting_end',
        'results_date',
        'status',
        'is_active',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
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
