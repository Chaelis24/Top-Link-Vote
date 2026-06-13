<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched to persist an activity-log entry in the background
 * and then broadcast the `AuditLogCreated` event so the admin
 * panel can refresh the audit trail in real time.
 */
class LogActivity implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $logData  Payload containing
     *                                          user_id, action, description, etc.
     */
    public function __construct(
        public array $logData
    ) {}

    /**
     * Creates the ActivityLog row and fires the broadcast event.
     */
    public function handle(): void
    {
        ActivityLog::create($this->logData);
        event(new \App\Events\AuditLogCreated());
    }
}
