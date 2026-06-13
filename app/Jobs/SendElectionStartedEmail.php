<?php

namespace App\Jobs;

use App\Models\Student;
use App\Notifications\ElectionAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends an election-started (or reminder) notification to every
 * active student. Processes students in chunks of 200 to avoid
 * overwhelming the mail queue.
 */
class SendElectionStartedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Iterates over active students and dispatches the ElectionAlert
     * notification to each student's associated User.
     */
    public function handle(): void
    {
        Student::where('status', 'active')
            ->with('user')
            ->chunk(200, function ($students) {
                foreach ($students as $student) {
                    if ($student->user) {
                        $student->user->notify(new ElectionAlert('started'));
                    }
                }
            });
    }
}
