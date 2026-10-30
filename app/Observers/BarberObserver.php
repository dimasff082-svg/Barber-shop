<?php

namespace App\Observers;

use App\Events\BarberAvailabilityChanged;
use App\Models\Barber;
use App\Services\Queue\QueueRules;
use App\Services\Realtime\PublishQueueUpdate;

class BarberObserver
{
    public function updated(Barber $barber): void
    {
        if ($barber->wasChanged('availability_status')) {
            app(PublishQueueUpdate::class)->afterCommit(
                new BarberAvailabilityChanged($barber->id, app(QueueRules::class)->businessNow()->toDateString()),
                $barber->getConnection(),
            );
        }
    }
}
