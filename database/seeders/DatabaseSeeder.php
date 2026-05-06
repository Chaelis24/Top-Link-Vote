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

        $studentRole = Role::where('name', 'student')->first();
        $candidateRole = Role::where('name', 'candidate')->first();

        if (!$candidateRole) {
            $this->command->error("Role 'candidate' not found!");
        }

        $michael = User::updateOrCreate(
            ['email' => 'michaelfarinas112@gmail.com'],
            [
                'name' => 'Michael Farinas',
                'password' => Hash::make('kel'),
            ]
        );

        $michael->roles()->sync([$studentRole->id, $candidateRole->id]);

        $michael->student()->updateOrCreate(
            ['student_id' => '23-0029'],
            [
                'first_name'  => 'Michael',
                'middle_name' => 'Buena',
                'last_name'   => 'Farinas',
                'suffix'      => 'Jr',
                'course'      => 'IT',
                'year_level'  => 3,
                'phone'       => '09515430735',
                'address'     => 'Cabanatuan City',
                'birthday'    => '2004-09-24',
                'gender'      => 'Male',
                'status'      => 'active',
            ]
        );

        // $this->call([
        //     ElectionCycleSeeder::class,
        //     PositionSeeder::class,
        //     UserAndStudentSeeder::class,
        //     CandidateSeeder::class,
        //     // VoteSeeder::class,
        // ]);
    }
}
