<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAndStudentSeeder extends Seeder
{
    public function run(): void
    {

        $studentRole = Role::where('name', 'Student')->first();
        $candidateRole = Role::where('name', 'Candidate')->first();

        $availableCourses = ['IT', 'HRMT', 'HST', 'ECT'];

        $erica = User::create([
            'name' => 'Erica Bianca Opena',
            'email' => 'mira.erica08@gmail.com',
            'password' => Hash::make('kai'),
        ]);
        $erica->roles()->attach($studentRole->id);
        $erica->student()->create([
            'student_id' => '2023-0025',
            'first_name' => 'Erica Bianca',
            'middle_name' => 'Mira',
            'last_name' => 'Opena',
            'course' => 'HRMT',
            'year_level' => 3,
            'birthday' => '2002-06-20',
            'gender' => 'Female',
            'status' => 'active',
        ]);

        $michael = User::create([
            'name' => 'Michael Farinas',
            'email' => 'michaelfarinas112@gmail.com',
            'password' => Hash::make('kel'),
        ]);
        $michael->roles()->attach([$studentRole->id, $candidateRole->id]);
        $michael->student()->create([
            'student_id' => '2023-0029',
            'first_name' => 'Michael',
            'middle_name' => 'Buena',
            'last_name' => 'Farinas',
            'course' => 'IT',
            'year_level' => 3,
            'birthday' => '2004-09-24',
            'gender' => 'Male',
            'status' => 'active',
        ]);

        // foreach ($availableCourses as $course) {
        //     for ($i = 1; $i <= 2; $i++) {
        //         $candidateUser = User::create([
        //             'name' => "Candidate $i - $course",
        //             'email' => "candidate{$i}_" . strtolower($course) . "@school.edu.ph",
        //             'password' => Hash::make('password123'),
        //         ]);

        //         $candidateUser->roles()->attach([$studentRole->id, $candidateRole->id]);

        //         $candidateUser->student()->create([
        //             'student_id' => "2026-" . strtoupper($course) . "-00" . $i,
        //             'first_name' => "Candidate $i",
        //             'last_name' => $course,
        //             'course' => $course,
        //             'year_level' => rand(2, 4),
        //             'gender' => $i % 2 == 0 ? 'Male' : 'Female',
        //             'status' => 'active',
        //         ]);
        //     }
        // }

        // for ($j = 1; $j <= 5; $j++) {
        //     $user = User::create([
        //         'name' => "Regular Student $j",
        //         'email' => "reg_student$j@school.edu.ph",
        //         'password' => Hash::make('password123'),
        //     ]);

        //     $user->roles()->attach($studentRole->id);

        //     $user->student()->create([
        //         'student_id' => "2026-REG-000$j",
        //         'first_name' => "Student",
        //         'last_name' => "Regular $j",
        //         'course' => $availableCourses[array_rand($availableCourses)],
        //         'year_level' => rand(1, 4),
        //         'gender' => rand(0, 1) ? 'Male' : 'Female',
        //         'status' => 'active',
        //     ]);
        // }
    }
}
