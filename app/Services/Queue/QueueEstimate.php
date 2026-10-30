<?php

namespace App\Services\Queue;

use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Builder;

class QueueEstimate
{
    public function __construct(private QueueRules $rules) {}

    /** @return array{ahead: int, minutes_min: ?int, minutes_max: ?int, label: string} */
    public function forTicket(QueueTicket $ticket): array
    {
        $label = 'Estimasi dapat berubah; bukan reservasi jam layanan.';
        if ($ticket->status->isTerminal() || $ticket->queue_date->toDateString() !== $this->rules->businessNow()->toDateString()) {
            return ['ahead' => 0, 'minutes_min' => null, 'minutes_max' => null, 'label' => $label];
        }
        if (in_array($ticket->status, [QueueStatus::Called, QueueStatus::InService], true)) {
            return ['ahead' => 0, 'minutes_min' => 0, 'minutes_max' => 0, 'label' => $label];
        }

        $preceding = QueueTicket::query()->where('barber_id', $ticket->barber_id)->whereKeyNot($ticket->id)
            ->where(function (Builder $query) use ($ticket) {
                $query->whereIn('status', [QueueStatus::Called->value, QueueStatus::InService->value])
                    ->orWhere(function (Builder $query) use ($ticket) {
                        $query->whereDate('queue_date', $ticket->queue_date->toDateString())
                            ->where('sequence_number', '<', $ticket->sequence_number)
                            ->whereIn('status', [QueueStatus::WaitingCheckin->value, QueueStatus::CheckedIn->value]);
                    });
            })->get();
        $minutes = 0;
        $uncertainMinutes = 0;
        $ahead = 0;
        $now = $this->rules->businessNow()->utc();
        foreach ($preceding as $other) {
            if ($other->status === QueueStatus::WaitingCheckin) {
                // These tickets may arrive in time, but cannot be called before check-in.
                $uncertainMinutes += $other->duration_snapshot;

                continue;
            }
            $ahead++;
            $remaining = $other->duration_snapshot;
            if ($other->status === QueueStatus::InService && $other->started_at !== null) {
                $remaining = max(0, $remaining - (int) floor($other->started_at->diffInMinutes($now, false)));
            }
            $minutes += $remaining;
        }

        return [
            'ahead' => $ahead,
            'minutes_min' => (int) floor($minutes * 0.8),
            'minutes_max' => (int) ceil(($minutes + $uncertainMinutes) * 1.2),
            'label' => $label,
        ];
    }
}
