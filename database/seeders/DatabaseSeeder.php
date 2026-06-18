<?php

namespace Database\Seeders;

use App\Models\{User, Role};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The root seeder that sets up roles, the admin account, a
 * default student/candidate account (Michael Farinas), and
 * delegates to CourseAndBlockSeeder for the course-block matrix.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'student', 'candidate'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin'),
            ]
        );

        $admin->assignRole('admin');
    }
}
