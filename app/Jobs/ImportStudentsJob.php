<?php

namespace App\Jobs;

use App\Models\{User, Student, Course, Block, Role};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{DB, Hash, Log};
use Carbon\Carbon;

/**
 * Processes the CSV rows uploaded by an admin, creating or updating
 * Course, Block, User, and Student records inside batched transactions.
 * Runs with a 10-minute timeout to handle large imports.
 */
class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    /**
     * @param  array<int, array<string, mixed>>  $rows  Parsed CSV rows.
     */
    public function __construct(protected array $rows)
    {
    }

    /**
     * Chunks rows into groups of 50 and processes each inside a
     * database transaction. Caches are cleared when all chunks complete.
     */
    public function handle(): void
    {
        $chunks = array_chunk($this->rows, 50);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $row) {
                    try {
                        $course = Course::firstOrCreate(['name' => $row['course']]);

                        $block = Block::firstOrCreate([
                            'course_id'  => $course->id,
                            'year_level' => $row['year_level'],
                            'section'    => $row['section'],
                        ]);

                        $user = User::firstOrCreate(
                            ['email' => $row['email']],
                            [
                                'name' => "{$row['first_name']} {$row['last_name']}",
                                'password' => Hash::make('P@ssword'),
                            ]
                        );

                        if (!empty($row['role'])) {
                            $role = Role::where('name', $row['role'])->first();
                            if ($role && !$user->hasRole($row['role'])) {
                                $user->assignRole($row['role']);
                            } elseif (!$role) {
                                Log::warning("Import: Role '{$row['role']}' not found for student {$row['student_id']}. Skipping role assignment.");
                            }
                        }

                        Student::updateOrCreate(
                            ['student_id' => $row['student_id']],
                            [
                                'user_id'     => $user->id,
                                'course_id'   => $course->id,
                                'block_id'    => $block->id,
                                'first_name'  => $row['first_name'],
                                'middle_name' => $row['middle_name'],
                                'last_name'   => $row['last_name'],
                                'suffix'      => $row['suffix'],
                                'phone'       => $row['phone'],
                                'address'     => $row['address'],
                                'birthday'    => $row['birthday'] ? Carbon::parse($row['birthday'])->format('Y-m-d') : null,
                                'gender'      => $row['gender'],
                                'status'      => $row['status'] ?? 'active',
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::error("Import Error for Student ID " . ($row['student_id'] ?? 'unknown') . ": " . $e->getMessage());
                        continue;
                    }
                }
            });
        }

        cache()->forget('students_stats');
        cache()->forget('admin_dashboard_data');
    }
}
