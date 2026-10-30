<?php

namespace App\Events;

final class QueueCompleted extends QueueInvalidated
{
    protected const TYPE = 'completed';
}
