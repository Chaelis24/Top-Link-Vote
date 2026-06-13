<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Defines a single election period (e.g. "AY 2025-2026 SSS Election").
 * Tracks the filing, campaign, and voting windows and caches the
 * currently active cycle for efficient lookups across the application.
 */
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

    /**
     * Retrieve the active cycle from cache (60-second TTL), falling
     * back to the latest cycle with status = 'active'.
     *
     * @return static|null
     */
    public static function getActiveCycle()
    {
        return Cache::remember('active_election_cycle', 60, function () {
            return self::where('status', 'active')->latest()->first();
        });
    }

    /**
     * All positions available for this election cycle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * All candidates who filed within this election cycle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * All votes cast during this election cycle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
