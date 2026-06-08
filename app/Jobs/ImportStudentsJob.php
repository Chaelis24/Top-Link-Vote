<?php

namespace App\Jobs;

use App\Models\{User, Student, Course, Block};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{DB, Hash, Log};
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(protected array $rows)
    {
        $this->onQueue('imports');
    }

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

                        try {
                            if ($role = Role::findByName($row['role'])) {
                                $user->syncRoles([$role->name]);
                            }
                        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
                            Log::warning("Import: Role '{$row['role']}' not found for student {$row['student_id']}. Skipping role assignment.");
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
