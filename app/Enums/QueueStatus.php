<?php

namespace App\Enums;

enum QueueStatus: string
{
    case WaitingCheckin = 'waiting_checkin';
    case CheckedIn = 'checked_in';
    case Called = 'called';
    case InService = 'in_service';
    case Completed = 'completed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::WaitingCheckin->value, self::CheckedIn->value, self::Called->value, self::InService->value];
    }

    public function isActive(): bool
    {
        return in_array($this->value, self::activeValues(), true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }
}
