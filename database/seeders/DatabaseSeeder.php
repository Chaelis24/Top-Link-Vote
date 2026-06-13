<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * The root seeder that sets up roles, the admin account, a
 * default student/candidate account (Michael Farinas), and
 * delegates to CourseAndBlockSeeder for the course-block matrix.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's foundational data:
     * - Spatie roles (admin, student, candidate)
     * - Admin user (admin@gmail.com)
     * - Default student + candidate (Michael Farinas)
     * - Course & block structure via CourseAndBlockSeeder
     */
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

        $candidateRole = Role::where('name', 'candidate')->first();

        if (!$candidateRole) {
            $this->command->error("Role 'candidate' not found!");
        }

        $this->call([
            CourseAndBlockSeeder::class,
        ]);

        $block = Block::where('year_level', 3)->where('section', 'A')->first();

        $michael = User::updateOrCreate(
            ['email' => 'michaelfarinas112@gmail.com'],
            [
                'name' => 'Michael Farinas',
                'password' => Hash::make('kel'),
            ]
        );

        $michael->assignRole(['student', 'candidate']);

        $michael->student()->updateOrCreate(
            ['student_id' => '23-0029'],
            [
                'first_name'  => 'Michael',
                'middle_name' => 'Buena',
                'last_name'   => 'Farinas',
                'suffix'      => 'Jr',
                'course_id'   => $block->course_id,
                'block_id'    => $block->id,
                'phone'       => '09515430735',
                'address'     => 'Cabanatuan City',
                'birthday'    => '2004-09-24',
                'gender'      => 'Male',
                'status'      => 'active',
            ]
        );
    }
}
