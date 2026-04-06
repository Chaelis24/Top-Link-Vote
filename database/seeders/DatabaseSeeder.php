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

        // $admin->student()->create([
        //     'student_id' => '001',
        //     'first_name' => 'System',
        //     'last_name' => 'Admin',
        //     'course' => 'IT',
        //     'year_level' => 4,
        //     'status' => 'active',
        // ]);

        $studentRole = Role::where('name', 'student')->first();
        $candidateRole = Role::where('name', 'candidate')->first();

        $michael = User::updateOrCreate(
            ['email' => 'michaelfarinas112@gmail.com'],
            [
                'name' => 'Michael Farinas',
                'password' => Hash::make('kel'),
            ]
        );

        $erica = User::updateOrCreate(
            ['email' => 'mira.erica08@gmail.com'],
            [
                'name' => 'Erica Opena',
                'password' => Hash::make('kai'),
            ]
        );

        $michael->roles()->sync([$studentRole->id, $candidateRole->id]);
        $erica->roles()->sync($studentRole->id);

        $michael->student()->updateOrCreate(
            ['student_id' => '23-0029'],
            [
                'first_name'  => 'Michael',
                'middle_name' => 'Buena',
                'last_name'   => 'Farinas',
                'course'      => 'IT',
                'year_level'  => 3,
                'birthday'    => '2004-09-24',
                'gender'      => 'Male',
                'status'      => 'active',
            ]
        );

        $erica->student()->updateOrCreate(
            ['student_id' => '23-0025'],
            [
                'first_name'  => 'Erica',
                'middle_name' => 'Mira',
                'last_name'   => 'Opena',
                'course'      => 'HRMT',
                'year_level'  => 3,
                'birthday'    => '2002-06-20',
                'gender'      => 'Female',
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
