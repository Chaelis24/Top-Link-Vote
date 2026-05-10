<?php

namespace App\Jobs;

use App\Models\Student;
use App\Notifications\ElectionAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendElectionStartedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $students = Student::where('status', 'active')->with('user')->get();

        foreach ($students as $student) {
            if ($student->user) {
                $student->user->notify(new ElectionAlert('started'));
            }
        }
    }
}
