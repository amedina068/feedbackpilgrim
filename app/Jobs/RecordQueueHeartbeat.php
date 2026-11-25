<?php

namespace App\Jobs;

use App\Support\Heartbeats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proves that a queue worker runs. The scheduler queues this job each minute.
 */
class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Heartbeats::recordQueue();
    }
}
