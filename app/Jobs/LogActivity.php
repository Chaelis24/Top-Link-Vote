<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LogActivity implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     * Dito natin tatanggapin ang array ng logs (user_id, action, etc.)
     */
    public function __construct(
        public array $logData
    ) {}

    /**
     * Execute the job.
     * Si Horizon ang tatawag sa handle() method na ito sa background.
     */
    public function handle(): void
    {
        ActivityLog::create($this->logData);
        event(new \App\Events\AuditLogCreated());
    }
}
