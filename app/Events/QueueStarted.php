<?php

namespace App\Events;

final class QueueStarted extends QueueInvalidated
{
    protected const TYPE = 'started';
}
