<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Student, Position, Candidate, Platform, ElectionCycle};

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('csv/sample-candidate.csv');

        if (!file_exists($path)) {
            $this->command->error("CSV file not found at: {$path}");
            return;
        }

        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            $this->command->error('No active election cycle found. Please seed or create a cycle first.');
            return;
        }

        if (($file = fopen($path, 'r')) !== false) {
            fgetcsv($file);

            $this->command->info('Importing candidates using Controller Logic...');
            $importedCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $row = array_map('trim', $row);

                $studentId              = $row[0] ?? '';
                $positionName           = $row[1] ?? '';
                $partyName              = $row[2] ?? '';
                $achievements           = $row[3] ?? '';
                $previousPosition       = $row[4] ?? '';
                $previousSchoolProjects = $row[5] ?? '';
                $averageGrade           = $row[6] !== '' ? $row[6] : null;
                $title                  = $row[7] ?? '';
                $tagline                = $row[8] ?? '';
                $agenda                 = $row[9] ?? '';

                if (empty($studentId) || empty($positionName)) {
                    continue;
                }

                $student = Student::where('student_id', $studentId)->first();

                if (!$student || !$student->user_id) {
                    $this->command->warn("Skipped student_id '{$studentId}': Not found or missing user account.");
                    continue;
                }

                $pos = Position::firstOrCreate(
                    [
                        'name' => $positionName,
                        'election_cycle_id' => $activeCycle->id,
                        'student_department' => $student->block->course_id ?? null,
                    ],
                    [
                        'max_candidates' => 10,
                        'max_winners' => 1,
                        'priority' => 1,
                        'is_active' => true,
                    ]
                );

                $cleanPreviousPositions = array_values(array_filter(array_map('trim', explode(',', $previousPosition))));
                $cleanPreviousProjects  = array_values(array_filter(array_map('trim', explode(',', $previousSchoolProjects))));
                $agendaArray            = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $agenda)))));

                $can = Candidate::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'election_cycle_id' => $activeCycle->id,
                    ],
                    [
                        'user_id' => $student->user_id,
                        'position_id' => $pos->id,
                        'party_name' => $partyName,
                        'achievements' => $achievements,
                        'previous_position' => $cleanPreviousPositions,
                        'previous_school_project' => $cleanPreviousProjects,
                        'average_grade' => $averageGrade,
                        'status' => 'approved',
                        'approved_at' => now(),
                    ]
                );

                Platform::updateOrCreate(
                    ['candidate_id' => $can->id],
                    [
                        'title' => $title,
                        'tagline' => $tagline,
                        'agenda' => $agendaArray,
                        'status' => 'approved',
                        'approved_at' => now(),
                    ]
                );

                $importedCount++;
            }

            fclose($file);

            cache()->forget('candidates_stats_' . $activeCycle->id);
            cache()->forget('admin_dashboard_data');

            $this->command->info("Successfully seeded {$importedCount} candidates!");
        }
    }
}
