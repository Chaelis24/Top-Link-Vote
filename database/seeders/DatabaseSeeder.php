<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. GUMAWA NG ROLES
        $adminRole = Role::create(['name' => 'admin']);
        $studentRole = Role::create(['name' => 'student']);

        // 2. CREATE SYSTEM ADMINISTRATOR
        $adminUser = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@evoting.com',
            'password' => Hash::make('admin'),
        ]);

        // I-attach ang Admin Role (Pivot Table)
        $adminUser->roles()->attach($adminRole);

        Student::create([
            'user_id' => $adminUser->id,
            'student_id' => '001',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'course' => 'IT',
            'year_level' => 4,
            'status' => 'active',
        ]);

        // 3. CREATE A SAMPLE STUDENT
        $studentUser = User::create([
            'name' => 'Erica Bianca Opena',
            'email' => 'mira.erica08@gmail.com',
            'password' => Hash::make('erica'),
        ]);

        // I-attach ang Student Role (Pivot Table)
        $studentUser->roles()->attach($studentRole);

        Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2023-0025',
            'first_name' => 'Erica Bianca',
            'middle_name' => 'Mira',
            'last_name' => 'Opena',
            'course' => 'IT',
            'year_level' => 3,
            'status' => 'active',
        ]);
    }
}
