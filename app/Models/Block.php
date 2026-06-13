<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a specific section within a course and year level.
 * e.g. "BSIT – 3rd Year – Section A".
 */
class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'year_level',
        'section',
    ];

    /**
     * All students assigned to this block.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    /**
     * The course that this block belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
