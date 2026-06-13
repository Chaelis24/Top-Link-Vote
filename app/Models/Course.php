<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An academic programme (e.g. "BS Information Technology").
 * Each course can have multiple year-level blocks and students.
 */
class Course extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * All year-level / section blocks under this course.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    /**
     * All students enrolled in this course.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
