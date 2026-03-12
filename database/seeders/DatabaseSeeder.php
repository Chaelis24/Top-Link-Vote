<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $adminRole = Role::where('name', 'admin')->first();

        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin'),
        ]);

        $admin->roles()->attach($adminRole);

        $admin->student()->create([
            'student_id' => '001',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'course' => 'IT',
            'year_level' => 4,
            'status' => 'active',
        ]);

        $this->call([
            ElectionCycleSeeder::class,
            PositionSeeder::class,
            UserAndStudentSeeder::class,
            CandidateSeeder::class,
            // VoteSeeder::class,
        ]);
    }
}
