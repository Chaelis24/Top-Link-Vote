<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Block;
use Illuminate\Database\Seeder;

class CourseAndBlockSeeder extends Seeder
{
    public function run(): void
    {
        $courseNames = ['IT', 'HRMT', 'HST', 'ECT'];
        $sections = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $years = [1, 2, 3];

        foreach ($courseNames as $name) {
            $course = Course::firstOrCreate(['name' => $name]);
            foreach ($years as $year) {
                foreach ($sections as $section) {
                    Block::firstOrCreate([
                        'course_id'  => $course->id,
                        'year_level' => $year,
                        'section'    => $section
                    ]);
                }
            }
        }
    }
}
