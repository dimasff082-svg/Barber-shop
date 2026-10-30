<?php

namespace App\Events;

final class QueueCalled extends QueueInvalidated
{
    protected const TYPE = 'called';
}
