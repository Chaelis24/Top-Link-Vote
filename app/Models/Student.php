<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'course',
        'status',
        'year_level',
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

    public function getFormattedYearAttribute()
    {
        return match ((string) $this->year_level) {
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            '4' => '4th Year',
            default => $this->year_level ?: 'Year Not Set',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
    public function latestVote()
    {
        return $this->hasOne(Vote::class)->latestOfMany();
    }
}
