<?php

namespace App\Events;

final class QueueCancelled extends QueueInvalidated
{
    protected const TYPE = 'cancelled';
}
