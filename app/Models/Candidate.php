<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Candidate extends Model
{
    use SoftDeletes, HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

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

    public function getIsProfileCompleteAttribute(): bool
    {
        return $this->isProfileComplete();
    }

    public function isProfileComplete(): bool
    {
        return empty($this->getIncompleteFields());
    }

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
        return $this->hasMany(Vote::class, 'candidate_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
