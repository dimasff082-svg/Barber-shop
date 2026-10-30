<?php

namespace App\Events;

final class QueueCheckedIn extends QueueInvalidated
{
    protected const TYPE = 'checked_in';
}
