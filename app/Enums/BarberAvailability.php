<?php

namespace App\Enums;

enum BarberAvailability: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Break = 'break';
    case OffDuty = 'off_duty';
}
