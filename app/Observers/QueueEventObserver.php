<?php

namespace App\Observers;

use App\Events\QueueCalled;
use App\Events\QueueCancelled;
use App\Events\QueueCheckedIn;
use App\Events\QueueCompleted;
use App\Events\QueueCreated;
use App\Events\QueueMissed;
use App\Events\QueueStarted;
use App\Events\QueueSummaryChanged;
use App\Models\QueueEvent;
use App\Services\Realtime\PublishQueueUpdate;

class QueueEventObserver
{
    public function created(QueueEvent $entry): void
    {
        $eventClass = match ($entry->event_type) {
            'created' => QueueCreated::class,
            'checked_in' => QueueCheckedIn::class,
            'called' => QueueCalled::class,
            'started' => QueueStarted::class,
            'completed' => QueueCompleted::class,
            'cancelled' => QueueCancelled::class,
            'missed' => QueueMissed::class,
            'grace_started' => QueueSummaryChanged::class,
            default => null,
        };
        if ($eventClass === null) {
            return;
        }
        $ticket = $entry->ticket()->firstOrFail();
        $event = new $eventClass($ticket->barber_id, $ticket->queue_date->toDateString());
        app(PublishQueueUpdate::class)->afterCommit($event, $entry->getConnection());
    }
}
