<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Candidate;

/**
 * Populates candidates with course-appropriate party names,
 * previous positions, school projects, and achievements
 * to create realistic profiles for development and testing.
 */
class CandidateProfileSeeder extends Seeder
{
    /**
     * Assign each candidate a party and profile details based on
     * their enrolled course, using a match expression for variety.
     */
    public function run()
    {
        $candidates = Candidate::all();

        foreach ($candidates as $candidate) {
            $course = $candidate->student->course->name ?? 'IT';

            $data = match ($course) {
                'IT' => [
                    'party' => 'Tech Alliance Party',
                    'positions' => ['Tech Lead', 'System Admin'],
                    'projects' => ['Coding Bootcamp', 'Cybersecurity Seminar'],
                    'achievements' => ['Best Capstone', 'Hackathon Winner']
                ],
                'HRMT' => [
                    'party' => 'Human Resource United',
                    'positions' => ['HR Officer', 'Event Coordinator'],
                    'projects' => ['HR Seminar', 'Recruitment Drive'],
                    'achievements' => ['Service Excellence Award', 'Leadership Badge']
                ],
                'HST' => [
                    'party' => 'Hospitality Heroes',
                    'positions' => ['Hospitality Lead', 'Tourism Coordinator'],
                    'projects' => ['Hotel Simulation', 'Tourism Expo'],
                    'achievements' => ['Culinary Excellence', 'Customer Service']
                ],
                'ECT' => [
                    'party' => 'Electronic Elite',
                    'positions' => ['Electronic Lead', 'Circuit Designer'],
                    'projects' => ['Robotics Expo', 'Electronic Workshop'],
                    'achievements' => ['Innovation Award', 'Tech Project Winner']
                ],
                default => [
                    'party' => 'General Coalition',
                    'positions' => ['Class President', 'Secretary'],
                    'projects' => ['General Assembly'],
                    'achievements' => ['Dean\'s Lister']
                ],
            };

            $candidate->update([
                'party_name' => $data['party'],
                'achievements' => $data['achievements'],
                'average_grade' => rand(100, 175) / 100,
                'photo' => null,
                'previous_position' => [
                    $data['positions'][array_rand($data['positions'])] . ' (2024)',
                    $data['positions'][array_rand($data['positions'])] . ' (2023)'
                ],
                'previous_school_project' => [
                    $data['projects'][array_rand($data['projects'])],
                    $data['projects'][array_rand($data['projects'])]
                ],
            ]);
        }
    }
}
