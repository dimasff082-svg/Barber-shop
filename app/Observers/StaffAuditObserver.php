<?php

namespace App\Observers;

use App\Events\QueueSummaryChanged;
use App\Models\StaffAuditEntry;
use App\Services\Queue\QueueRules;
use App\Services\Realtime\PublishQueueUpdate;

class StaffAuditObserver
{
    public function created(StaffAuditEntry $entry): void
    {
        if ($entry->action === 'barber.availability_changed') {
            return; // The Barber observer already publishes this actual state change.
        }
        $barberId = $entry->target_type === 'barber' && ctype_digit((string) $entry->target_id)
            ? (int) $entry->target_id : null;
        app(PublishQueueUpdate::class)->afterCommit(
            new QueueSummaryChanged($barberId, app(QueueRules::class)->businessNow()->toDateString()),
            $entry->getConnection(),
        );
    }
}
