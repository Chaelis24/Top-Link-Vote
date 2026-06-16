<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * A student who has filed their candidacy for a specific position
 * within an election cycle. Tracks approval status, campaign
 * materials (photo, achievements, platform), and vote count.
 */
class Candidate extends Model
{
    use SoftDeletes, HasFactory;

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

    protected $appends = ['is_profile_complete'];

    /**
     * Accessor for is_profile_complete appended attribute.
     *
     * @return bool
     */
    public function getIsProfileCompleteAttribute(): bool
    {
        return $this->isProfileComplete();
    }

    /**
     * Returns true when the candidate has submitted a photo, grade,
     * achievements, and a fully filled-out platform record.
     *
     * @return bool
     */
    public function isProfileComplete(): bool
    {
        return empty($this->getIncompleteFields());
    }

    /**
     * Checks all required profile fields and returns the names of
     * any that are still missing or empty.
     *
     * @return array<int, string>
     */
    public function getIncompleteFields(): array
    {
        $platform = $this->relationLoaded('platforms')
            ? $this->platforms->first()
            : $this->platforms()->first();

        $check = [
            'candidate_photo' => !empty($this->photo),
            'candidate_grade' => !empty($this->average_grade) && $this->average_grade > 0,
            'candidate_achievements' => !empty($this->achievements) && count((array)$this->achievements) > 0,
            'has_platform_record' => $platform !== null,
            'platform_title' => $platform && !empty($platform->title),
            'platform_tagline' => $platform && !empty($platform->tagline),
            'platform_agenda' => $platform && is_array($platform->agenda) && count($platform->agenda) > 0,
        ];

        $missing = array_keys(array_filter($check, fn($value) => $value === false));

        if (!empty($missing)) {
            Log::info("Candidate ID {$this->id} Incomplete fields:", $missing);
        }

        return $missing;
    }


    /**
     * The student record associated with this candidacy.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Platform statements submitted by this candidate (one per position).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
    }

    /**
     * The election cycle this candidacy belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function electionCycle(): BelongsTo
    {
        return $this->belongsTo(ElectionCycle::class);
    }

    /**
     * The position being run for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * All votes cast for this candidate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'candidate_id', 'id');
    }

    /**
     * The underlying user account for this candidate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
