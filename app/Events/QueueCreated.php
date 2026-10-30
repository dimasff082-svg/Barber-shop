<?php

namespace App\Events;

final class QueueCreated extends QueueInvalidated
{
    protected const TYPE = 'created';
}
