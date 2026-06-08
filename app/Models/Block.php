<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'year_level',
        'section',
    ];

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
