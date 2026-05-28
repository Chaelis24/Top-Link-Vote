<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['name'];

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
