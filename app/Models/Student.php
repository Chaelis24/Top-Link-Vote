<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The core student record linked to a User account. Tracks enrolment
 * details (course, block), profile information, and voting status.
 */
class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'course_id',
        'block_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'status',
        'phone',
        'address',
        'birthday',
        'gender',
        'photo',
        'has_voted',
        'voted_at',
        'vote_reference'
    ];

    protected $casts = [
        'birthday' => 'date',
        'voted_at' => 'datetime',
        'has_voted' => 'boolean',
    ];

    /**
     * Accessor that formats the numeric year level into a human-readable
     * string (e.g. "1st Year", "2nd Year").
     *
     * @return string
     */
    public function getFormattedYearAttribute()
    {
        $year = $this->block ? $this->block->year_level : null;

        return match ((string) $year) {
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            default => $year ? $year . ' Year' : 'Year Not Set',
        };
    }

    /**
     * The user account associated with this student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The candidacy record if this student has filed to run.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    /**
     * All votes cast by this student across election cycles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * The most recent vote cast by this student (if any).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestVote()
    {
        return $this->hasOne(Vote::class)->latestOfMany();
    }

    /**
     * The block (year level + section) this student belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    /**
     * The academic course this student is enrolled in.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
