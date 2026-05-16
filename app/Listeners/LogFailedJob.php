<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class LogFailedJob
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function handle(JobFailed $event): void
    {
        $jobClass = get_class($event->job->resolveName()
            ? $event->job
            : $event->job);

        // Try to get a clean class name from the payload
        $payload = json_decode($event->job->getRawBody(), true);
        $displayClass = $payload['displayName'] ?? $payload['job'] ?? 'UnknownJob';

        Log::error('Queue job failed permanently', [
            'job'        => $displayClass,
            'connection' => $event->connectionName,
            'queue'      => $event->job->getQueue(),
            'exception'  => $event->exception->getMessage(),
        ]);

        $this->auditLogService->log(
            entityType: 'queue_job',
            entityId: null,
            action: 'job_failed',
            oldValues: [],
            newValues: [
                'job_class'  => $displayClass,
                'queue'      => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'error'      => $event->exception->getMessage(),
            ],
        );
    }
}
