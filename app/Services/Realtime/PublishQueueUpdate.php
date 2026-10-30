<?php

namespace App\Services\Realtime;

use App\Events\QueueInvalidated;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishQueueUpdate
{
    public function afterCommit(QueueInvalidated $event, Connection $connection): void
    {
        try {
            $connection->afterCommit(function () use ($event): void {
                try {
                    Event::dispatch($event);
                } catch (Throwable $exception) {
                    // An unavailable queue/WebSocket must never fail a committed ticket.
                    $this->recordFailure($event, $exception);
                }
            });
        } catch (Throwable $exception) {
            $this->recordFailure($event, $exception);
        }
    }

    private function recordFailure(QueueInvalidated $event, Throwable $exception): void
    {
        try {
            // Do not log exception messages: transport errors can contain credentials.
            Log::warning('Realtime delivery unavailable; clients will refetch using polling.', [
                'event' => $event->broadcastAs(), 'exception_type' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging outages must not change the result of a valid transaction either.
        }
    }
}
