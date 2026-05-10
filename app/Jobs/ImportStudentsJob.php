<?php

namespace App\Jobs;

use App\Models\{User, Student};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{DB, Hash, Log};

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(protected array $rows) {}

    public function handle(): void
    {
        $chunks = array_chunk($this->rows, 50);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $row) {
                    try {
                        $user = User::firstOrCreate(
                            ['email' => $row['email']],
                            [
                                'name' => "{$row['first_name']} {$row['last_name']}",
                                'password' => Hash::make('student'),
                            ]
                        );

                        if (!$user->hasRole('student')) {
                            $user->assignRole('student');
                        }

                        Student::updateOrCreate(
                            ['student_id' => $row['student_id']],
                            [
                                'user_id' => $user->id,
                                'first_name' => $row['first_name'],
                                'middle_name' => $row['middle_name'],
                                'last_name' => $row['last_name'],
                                'suffix' => $row['suffix'],
                                'course' => $row['course'],
                                'year_level' => $row['year_level'],
                                'phone' => $row['phone'],
                                'address' => $row['address'],
                                'birthday' => $row['birthday'] ? \Carbon\Carbon::parse($row['birthday'])->format('Y-m-d') : null,
                                'gender' => $row['gender'],
                                'status' => 'active',
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::error("Import Error for Student ID {$row['student_id']}: " . $e->getMessage());
                        continue;
                    }
                }
            });
        }

        cache()->forget('students_stats');
        cache()->forget('admin_dashboard_data');
    }
}
