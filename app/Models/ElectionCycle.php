<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ElectionCycle extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'name',
        'academic_year',
        'filing_start',
        'filing_end',
        'campaign_start',
        'campaign_end',
        'voting_start',
        'voting_end',
        'results_date',
        'status',
        'notifications_sent',
    ];

    protected $casts = [
        'filing_start' => 'date',
        'filing_end' => 'date',
        'campaign_start' => 'date',
        'campaign_end' => 'date',
        'voting_start' => 'datetime',
        'voting_end' => 'datetime',
        'results_date' => 'datetime',
    ];

    public static function getActiveCycle()
    {
        return Cache::remember('active_election_cycle', 60, function () {
            return self::where('status', 'active')->latest()->first();
        });
    }

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
