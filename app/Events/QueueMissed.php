<?php

namespace App\Events;

final class QueueMissed extends QueueInvalidated
{
    protected const TYPE = 'missed';
}
