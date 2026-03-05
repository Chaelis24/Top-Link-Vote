<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'year_level',
        'photo',
        'status',
        'has_voted',
        'voted_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
