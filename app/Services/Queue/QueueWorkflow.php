<?php

namespace App\Services\Queue;

use App\Enums\BarberAvailability;
use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\QrCode;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueWorkflow
{
    public function __construct(private QueueRules $rules) {}

    public function callNext(User $actor, int $barberId): ?QueueTicket
    {
        return DB::transaction(function () use ($actor, $barberId) {
            $barber = Barber::query()->lockForUpdate()->findOrFail($barberId);
            $this->authorize($actor, $barber);
            $now = $this->rules->businessNow();
            $this->expireForBarber($barber, $now);

            if (! $barber->is_active || ! $barber->user->canAccessStaffArea() || ! $barber->user->isBarber()
                || $barber->availability_status !== BarberAvailability::Available) {
                $this->invalid('Barber belum siap untuk memanggil antrean.');
            }
            if ($this->hasProcessingTicket($barber)) {
                $this->invalid('Selesaikan antrean yang sedang dipanggil atau dilayani terlebih dahulu.');
            }

            $ticket = QueueTicket::query()->where('barber_id', $barber->id)
                ->whereDate('queue_date', $now->toDateString())->where('status', QueueStatus::CheckedIn->value)
                ->orderBy('sequence_number')->lockForUpdate()->first();
            if ($ticket === null) {
                return null;
            }

            $skipped = QueueTicket::query()->where('barber_id', $barber->id)
                ->whereDate('queue_date', $now->toDateString())->where('status', QueueStatus::WaitingCheckin->value)
                ->where('sequence_number', '<', $ticket->sequence_number)->whereNull('grace_started_at')
                ->orderBy('sequence_number')->lockForUpdate()->get();
            foreach ($skipped as $waiting) {
                $waiting->forceFill([
                    'grace_started_at' => $now->utc(),
                    'grace_expires_at' => $now->addMinutes($this->rules->graceMinutes())->utc(),
                ])->save();
                $this->record($waiting, 'grace_started', QueueStatus::WaitingCheckin, $actor->id);
            }

            return $this->transition($ticket, QueueStatus::Called, 'called', $now, $actor->id, ['called_at' => $now->utc()]);
        }, 5);
    }

    public function start(User $actor, string $ticketId): QueueTicket
    {
        return $this->withTicket($ticketId, function (QueueTicket $ticket, Barber $barber) use ($actor) {
            $this->authorize($actor, $barber);
            $now = $this->rules->businessNow();
            if ($ticket->status !== QueueStatus::Called || $ticket->queue_date->toDateString() !== $now->toDateString()) {
                $this->invalid('Hanya antrean yang sedang dipanggil hari ini dapat mulai dilayani.');
            }
            $barber->forceFill(['availability_status' => BarberAvailability::Busy])->save();

            return $this->transition($ticket, QueueStatus::InService, 'started', $now, $actor->id, ['started_at' => $now->utc()]);
        });
    }

    public function complete(User $actor, string $ticketId): QueueTicket
    {
        return $this->withTicket($ticketId, function (QueueTicket $ticket, Barber $barber) use ($actor) {
            $this->authorize($actor, $barber);
            if ($ticket->status !== QueueStatus::InService) {
                $this->invalid('Hanya layanan yang sedang berlangsung dapat diselesaikan.');
            }
            $now = $this->rules->businessNow();
            $barber->forceFill(['availability_status' => BarberAvailability::Available])->save();

            return $this->transition($ticket, QueueStatus::Completed, 'completed', $now, $actor->id, ['completed_at' => $now->utc()]);
        });
    }

    public function checkIn(string $ticketId, string $qrCode): QueueTicket
    {
        $expired = false;
        $ticket = $this->withTicket($ticketId, function (QueueTicket $ticket, Barber $barber) use ($qrCode, &$expired) {
            if (! QrCode::query()->where('code', $qrCode)->where('is_active', true)->exists()) {
                $this->invalid('QR lokasi tidak aktif atau tidak ditemukan.');
            }
            $now = $this->rules->businessNow();
            if ($ticket->source !== QueueSource::Online) {
                $this->invalid('Check-in ini hanya berlaku untuk tiket online.');
            }
            if ($this->isExpired($ticket, $now)) {
                $expired = true;

                return $this->miss($ticket, $now);
            }
            if ($ticket->queue_date->toDateString() !== $now->toDateString() || ! $ticket->status->isActive()) {
                $this->invalid('Tiket sudah tidak berlaku untuk check-in.');
            }
            if (in_array($ticket->status, [QueueStatus::CheckedIn, QueueStatus::Called, QueueStatus::InService], true)) {
                return $ticket;
            }

            return $this->transition($ticket, QueueStatus::CheckedIn, 'checked_in', $now, null, ['checked_in_at' => $now->utc()]);
        });
        // Reject only after the missed transition has committed, so late arrivals
        // cannot roll back expiry and keep an active phone reserved indefinitely.
        if ($expired) {
            $this->invalid('Masa berlaku atau toleransi check-in tiket telah berakhir.');
        }

        return $ticket;
    }

    public function cancelGuest(string $ticketId): QueueTicket
    {
        return $this->withTicket($ticketId, function (QueueTicket $ticket, Barber $barber) {
            if ($ticket->status === QueueStatus::Cancelled) {
                return $ticket;
            }
            $now = $this->rules->businessNow();
            if ($ticket->queue_date->toDateString() !== $now->toDateString()
                || ! in_array($ticket->status, [QueueStatus::WaitingCheckin, QueueStatus::CheckedIn], true)) {
                $this->invalid('Tiket hanya dapat dibatalkan sebelum dipanggil dan pada tanggal berlakunya.');
            }

            return $this->transition($ticket, QueueStatus::Cancelled, 'cancelled', $now, null,
                ['cancelled_at' => $now->utc(), 'cancellation_reason' => 'Dibatalkan oleh pemilik tiket.'], 'Dibatalkan oleh pemilik tiket.');
        });
    }

    public function cancelByAdmin(User $actor, string $ticketId, string $reason): QueueTicket
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi, maksimal 500 karakter.']);
        }

        return $this->withTicket($ticketId, function (QueueTicket $ticket, Barber $barber) use ($actor, $reason) {
            $this->authorize($actor, $barber, true);
            if (! $ticket->status->isActive()) {
                $this->invalid('Tiket terminal tidak dapat dibatalkan kembali.');
            }
            if (in_array($ticket->status, [QueueStatus::Called, QueueStatus::InService], true)) {
                $barber->forceFill(['availability_status' => BarberAvailability::Available])->save();
            }
            $now = $this->rules->businessNow();

            return $this->transition($ticket, QueueStatus::Cancelled, 'cancelled', $now, $actor->id,
                ['cancelled_at' => $now->utc(), 'cancellation_reason' => $reason], $reason);
        });
    }

    public function expireDue(): int
    {
        $now = $this->rules->businessNow();
        $barberIds = QueueTicket::query()->whereIn('status', [QueueStatus::WaitingCheckin->value, QueueStatus::CheckedIn->value, QueueStatus::Called->value])
            ->where(function ($query) use ($now) {
                $query->whereDate('queue_date', '<', $now->toDateString())
                    ->orWhere(function ($query) use ($now) {
                        $query->where('status', QueueStatus::WaitingCheckin->value)->where('grace_expires_at', '<=', $now->utc());
                    });
            })->distinct()->orderBy('barber_id')->pluck('barber_id');
        $expired = 0;
        foreach ($barberIds as $barberId) {
            $expired += DB::transaction(function () use ($barberId, $now) {
                $barber = Barber::query()->whereKey($barberId)->lockForUpdate()->firstOrFail();

                return $this->expireForBarber($barber, $now);
            }, 5);
        }

        return $expired;
    }

    public function markMissed(User $actor, string $ticketId): QueueTicket
    {
        return $this->withTicket($ticketId, function (QueueTicket $ticket, Barber $barber) use ($actor) {
            $this->authorize($actor, $barber);
            $now = $this->rules->businessNow();
            if ($ticket->status !== QueueStatus::WaitingCheckin || ! $this->isExpired($ticket, $now)) {
                $this->invalid('Hanya tiket belum hadir yang sudah melewati toleransi atau tanggal berlaku dapat ditandai terlewat.');
            }

            return $this->transition($ticket, QueueStatus::Missed, 'missed', $now, $actor->id,
                ['missed_at' => $now->utc()], 'Ditandai staff setelah masa toleransi atau tanggal berlaku berakhir.');
        });
    }

    private function expireForBarber(Barber $barber, CarbonImmutable $now): int
    {
        $tickets = QueueTicket::query()->where('barber_id', $barber->id)
            ->whereIn('status', [QueueStatus::WaitingCheckin->value, QueueStatus::CheckedIn->value, QueueStatus::Called->value])
            ->where(function ($query) use ($now) {
                $query->whereDate('queue_date', '<', $now->toDateString())
                    ->orWhere(function ($query) use ($now) {
                        $query->where('status', QueueStatus::WaitingCheckin->value)->where('grace_expires_at', '<=', $now->utc());
                    });
            })->orderBy('id')->lockForUpdate()->get();
        $count = 0;
        foreach ($tickets as $ticket) {
            if ($this->isExpired($ticket, $now)) {
                $this->miss($ticket, $now);
                $count++;
            }
        }

        return $count;
    }

    private function isExpired(QueueTicket $ticket, CarbonImmutable $now): bool
    {
        if (! in_array($ticket->status, [QueueStatus::WaitingCheckin, QueueStatus::CheckedIn, QueueStatus::Called], true)) {
            return false;
        }

        return $ticket->queue_date->toDateString() < $now->toDateString()
            || ($ticket->status === QueueStatus::WaitingCheckin && $ticket->grace_expires_at !== null && $ticket->grace_expires_at->lessThanOrEqualTo($now));
    }

    private function miss(QueueTicket $ticket, CarbonImmutable $now): QueueTicket
    {
        return $this->transition($ticket, QueueStatus::Missed, 'missed', $now, null, ['missed_at' => $now->utc()],
            $ticket->queue_date->toDateString() < $now->toDateString() ? 'Tanggal bisnis telah berakhir.' : 'Masa toleransi check-in telah berakhir.');
    }

    /** @param callable(QueueTicket, Barber): QueueTicket $action */
    private function withTicket(string $ticketId, callable $action): QueueTicket
    {
        // Read only the immutable ownership key before acquiring the canonical
        // barber -> ticket locks shared with creation and availability changes.
        $barberId = QueueTicket::query()->whereKey($ticketId)->firstOrFail()->barber_id;

        return DB::transaction(function () use ($ticketId, $barberId, $action) {
            $barber = Barber::query()->lockForUpdate()->findOrFail($barberId);
            $ticket = QueueTicket::query()->where('barber_id', $barber->id)->lockForUpdate()->findOrFail($ticketId);

            return $action($ticket, $barber);
        }, 5);
    }

    private function authorize(User $actor, Barber $barber, bool $adminOnly = false): void
    {
        $current = User::query()->find($actor->id);
        if ($current === null || ! $current->canAccessStaffArea()
            || (! $current->isAdmin() && ($adminOnly || ! $current->isBarber() || $barber->user_id !== $current->id || ! $barber->is_active))) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk memproses antrean barber ini.');
        }
    }

    private function hasProcessingTicket(Barber $barber): bool
    {
        return QueueTicket::query()->where('barber_id', $barber->id)->whereIn('status', [QueueStatus::Called->value, QueueStatus::InService->value])->exists();
    }

    /** @param array<string, mixed> $attributes */
    private function transition(QueueTicket $ticket, QueueStatus $status, string $event, CarbonImmutable $now, ?int $actorId, array $attributes = [], ?string $reason = null): QueueTicket
    {
        $previous = $ticket->status;
        $ticket->forceFill([...$attributes, 'status' => $status])->save();
        $this->record($ticket, $event, $previous, $actorId, $reason, $now);

        return $ticket;
    }

    private function record(QueueTicket $ticket, string $event, QueueStatus $previous, ?int $actorId, ?string $reason = null, ?CarbonImmutable $now = null): void
    {
        QueueEvent::query()->create([
            'queue_ticket_id' => $ticket->id,
            'actor_user_id' => $actorId,
            'event_type' => $event,
            'previous_status' => $previous,
            'new_status' => $ticket->status,
            'reason' => $reason,
            'created_at' => ($now ?? $this->rules->businessNow())->utc(),
        ]);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['queue' => $message]);
    }
}
