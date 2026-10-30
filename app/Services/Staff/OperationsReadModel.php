<?php

namespace App\Services\Staff;

use App\Enums\BarberAvailability;
use App\Enums\QueueStatus;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queue\QueueRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OperationsReadModel
{
    public function __construct(private QueueRules $rules) {}

    /** @return Builder<QueueTicket> */
    public function scoped(User $actor): Builder
    {
        $query = QueueTicket::query();
        if (! $actor->isAdmin()) {
            $barber = $actor->barber;
            $barber === null ? $query->whereRaw('1 = 0') : $query->where('barber_id', $barber->id);
        }

        return $query;
    }

    /** @return array<string, int> */
    public function statistics(User $actor, ?string $date = null): array
    {
        $counts = $this->scoped($actor)->whereDate('queue_date', $date ?? $this->rules->businessNow()->toDateString())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $result = ['total' => 0];
        foreach (QueueStatus::cases() as $status) {
            $result[$status->value] = (int) $counts->get($status->value, 0);
            $result['total'] += $result[$status->value];
        }
        $result['waiting'] = $result['waiting_checkin'] + $result['checked_in'];

        return $result;
    }

    /** @return array<string, mixed> */
    public function dashboard(User $actor): array
    {
        $date = $this->rules->businessNow()->toDateString();
        $barber = $actor->isAdmin() ? null : $actor->barber;
        $processing = $this->scoped($actor)->whereIn('status', ['called', 'in_service'])->with(['barber', 'service'])->orderBy('created_at')->get();
        $next = $barber === null ? null : $this->scoped($actor)->whereDate('queue_date', $date)->where('status', 'checked_in')
            ->orderBy('sequence_number')->with(['barber', 'service'])->first();

        return [
            'date' => $date, 'timezone' => (string) config('barberflow.timezone'),
            'stats' => $this->statistics($actor),
            'active_barbers' => $actor->isAdmin() ? Barber::query()->where('is_active', true)
                ->whereHas('user', fn (Builder $query) => $query->where('is_active', true)->where('role', UserRole::Barber->value))->count() : 0,
            'barber' => $barber === null ? null : $this->barber($barber),
            'profile_missing' => ! $actor->isAdmin() && $barber === null,
            'processing' => $processing->map(fn (QueueTicket $ticket) => $this->ticket($ticket, $actor))->all(),
            'next' => $next === null ? null : $this->ticket($next, $actor),
            'can_call' => $barber !== null && $barber->is_active && $barber->availability_status === BarberAvailability::Available
                && $processing->isEmpty() && $next !== null,
            'barbers' => $actor->isAdmin() ? Barber::query()->with('user')->orderBy('id')->get()->map(fn (Barber $item) => $this->barber($item))->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    public function listing(User $actor, Request $request, bool $history = false): array
    {
        $date = $request->string('date')->toString() ?: $this->rules->businessNow()->toDateString();
        $query = $this->scoped($actor)->whereDate('queue_date', $date)->with(['barber', 'service']);
        if ($history) {
            $query->whereIn('status', ['completed', 'cancelled', 'missed']);
        }
        foreach (['status', 'source'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }
        if ($actor->isAdmin() && $request->filled('barber_id')) {
            $query->where('barber_id', $request->integer('barber_id'));
        }
        $tickets = $query->orderBy('barber_id')->orderBy('sequence_number')->paginate(20)->withQueryString();
        $tickets->through(fn (QueueTicket $ticket) => $this->ticket($ticket, $actor));

        return [
            'tickets' => $tickets,
            'filters' => ['date' => $date, 'barber_id' => $actor->isAdmin() ? $request->string('barber_id')->toString() : '',
                'status' => $request->string('status')->toString(), 'source' => $request->string('source')->toString()],
            'is_admin' => $actor->isAdmin(), 'history' => $history,
            'barbers' => $actor->isAdmin() ? Barber::query()->orderBy('name')->get()->map(fn (Barber $item) => ['id' => $item->id, 'name' => $item->name])->all() : [],
            'stats' => $this->statistics($actor, $date),
        ];
    }

    /** @return array<string, mixed> */
    public function ticket(QueueTicket $ticket, User $actor): array
    {
        $ticket->loadMissing(['barber', 'service']);
        $today = $ticket->queue_date->toDateString() === $this->rules->businessNow()->toDateString();
        $canMiss = $ticket->status === QueueStatus::WaitingCheckin && ($ticket->queue_date->toDateString() < $this->rules->businessNow()->toDateString()
            || ($ticket->grace_expires_at !== null && $ticket->grace_expires_at->lessThanOrEqualTo($this->rules->businessNow())));

        return [
            'id' => $ticket->id, 'queue_number' => $ticket->queue_number, 'queue_date' => $ticket->queue_date->toDateString(),
            'customer_name' => $ticket->customer_name, 'phone_masked' => '•••• '.substr($ticket->normalized_phone, -4),
            'barber' => ['id' => $ticket->barber_id, 'name' => $ticket->barber->name],
            'service' => ['id' => $ticket->service_id, 'name' => $ticket->service->name],
            'source' => $ticket->source->value, 'status' => $ticket->status->value,
            'price' => $ticket->price_snapshot, 'duration_minutes' => $ticket->duration_snapshot,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(), 'called_at' => $ticket->called_at?->toIso8601String(),
            'started_at' => $ticket->started_at?->toIso8601String(), 'completed_at' => $ticket->completed_at?->toIso8601String(),
            'grace_expires_at' => $ticket->grace_expires_at?->toIso8601String(), 'cancellation_reason' => $ticket->cancellation_reason,
            'actions' => ['start' => $today && $ticket->status === QueueStatus::Called,
                'complete' => $ticket->status === QueueStatus::InService, 'missed' => $canMiss,
                'cancel' => $actor->isAdmin() && $ticket->status->isActive()],
            'detail_url' => route($actor->isAdmin() ? 'admin.queues.show' : 'barber.queues.show', $ticket->id),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function events(QueueTicket $ticket): array
    {
        return array_values($ticket->events()->with('actor')->orderBy('id')->get()->map(fn (QueueEvent $event) => [
            'id' => $event->id, 'type' => $event->event_type, 'previous_status' => $event->previous_status?->value,
            'new_status' => $event->new_status->value, 'actor' => $event->actor->name ?? 'Pelanggan / sistem',
            'reason' => $event->reason, 'created_at' => $event->created_at->toIso8601String(),
        ])->all());
    }

    /** @return array<string, mixed> */
    public function barber(Barber $barber): array
    {
        return ['id' => $barber->id, 'name' => $barber->name, 'availability' => $barber->availability_status->value,
            'is_active' => $barber->is_active && $barber->user->is_active, 'accepts_queue' => $barber->accepts_queue];
    }

    /** @return array<string, mixed> */
    public function report(string $from, string $to): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
        if ($start === null || $end === null || $end->lessThan($start) || $start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['to' => 'Pilih rentang berurutan maksimal 366 hari.']);
        }
        $query = QueueTicket::query()->whereDate('queue_date', '>=', $from)->whereDate('queue_date', '<=', $to);
        $rows = $query->with(['barber', 'service'])->lazyById(500);
        $days = [];
        $barbers = [];
        $services = [];
        $waitTotal = 0.0;
        $waitSamples = 0;
        $totals = ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'missed' => 0, 'online' => 0, 'onsite' => 0];
        foreach ($rows as $ticket) {
            $totals['total']++;
            $day = $ticket->queue_date->toDateString();
            $days[$day] ??= ['date' => $day, 'total' => 0, 'completed' => 0, 'cancelled' => 0, 'missed' => 0, 'online' => 0, 'onsite' => 0];
            $days[$day]['total']++;
            $days[$day][$ticket->source->value]++;
            $totals[$ticket->source->value]++;
            if (in_array($ticket->status, [QueueStatus::Completed, QueueStatus::Cancelled, QueueStatus::Missed], true)) {
                $days[$day][$ticket->status->value]++;
                $totals[$ticket->status->value]++;
            }
            if ($ticket->status === QueueStatus::Completed) {
                $barbers[$ticket->barber_id] ??= ['id' => $ticket->barber_id, 'name' => $ticket->barber->name, 'completed' => 0];
                $services[$ticket->service_id] ??= ['id' => $ticket->service_id, 'name' => $ticket->service->name, 'completed' => 0];
                $barbers[$ticket->barber_id]['completed']++;
                $services[$ticket->service_id]['completed']++;
                if ($ticket->checked_in_at !== null && $ticket->started_at !== null && $ticket->started_at->greaterThanOrEqualTo($ticket->checked_in_at)) {
                    $waitTotal += $ticket->checked_in_at->diffInMinutes($ticket->started_at);
                    $waitSamples++;
                }
            }
        }

        ksort($days);

        return ['range' => compact('from', 'to'), 'totals' => [...$totals, 'avg_wait_minutes' => $waitSamples === 0 ? null : round($waitTotal / $waitSamples, 1), 'wait_samples' => $waitSamples],
            'days' => array_values($days), 'barbers' => array_values($barbers), 'services' => array_values($services),
            'wait_definition' => 'Rata-rata dari check-in sampai mulai layanan, hanya tiket selesai dengan timestamp yang valid.'];
    }
}
