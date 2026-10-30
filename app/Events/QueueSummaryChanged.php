<?php

namespace App\Events;

final class QueueSummaryChanged extends QueueInvalidated
{
    protected const TYPE = 'summary_changed';
}
