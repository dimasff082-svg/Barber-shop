<?php

namespace App\Events;

final class BarberAvailabilityChanged extends QueueInvalidated
{
    protected const TYPE = 'availability_changed';
}
