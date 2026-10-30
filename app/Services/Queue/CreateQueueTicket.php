<?php

namespace App\Services\Queue;

use App\Data\CreatedQueueTicket;
use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\QrCode;
use App\Models\QueueCounter;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateQueueTicket
{
    public function __construct(private QueueRules $rules, private PhoneNormalizer $phones) {}

    /** @param array<string, mixed> $input */
    public function create(array $input, string $ownerSecret): CreatedQueueTicket
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : $input['name'] ?? null;
        $input['source'] = ($input['source'] ?? null) instanceof QueueSource ? $input['source']->value : $input['source'] ?? null;
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'barber_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'source' => ['required', Rule::enum(QueueSource::class)],
            'qr_code' => ['nullable', 'string', 'max:64'],
        ])->validate();

        if (strlen($ownerSecret) < 32) {
            throw new \InvalidArgumentException('A server-generated owner secret is required.');
        }

        $phone = $this->phones->normalize((string) $data['phone']);
        $key = strtolower((string) $data['idempotency_key']);
        $ownerHash = hash('sha256', $ownerSecret);
        $source = QueueSource::from((string) $data['source']);
        $fingerprint = hash('sha256', json_encode([
            'name' => $data['name'], 'phone' => $phone,
            'barber_id' => (int) $data['barber_id'], 'service_id' => (int) $data['service_id'],
            'source' => $source->value, 'qr_code' => $source === QueueSource::Onsite ? ($data['qr_code'] ?? null) : null,
        ], JSON_THROW_ON_ERROR));

        if (($existing = QueueTicket::query()->where('idempotency_key', $key)->first()) !== null) {
            return $this->retry($existing, $ownerHash, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($data, $phone, $key, $ownerHash, $source, $fingerprint) {
                // Barber is the first lock for every operation on its queue.
                $barber = Barber::query()->whereKey((int) $data['barber_id'])->lockForUpdate()->first();
                $service = Service::query()->whereKey((int) $data['service_id'])->lockForUpdate()->first();
                if ($barber === null || $service === null) {
                    throw ValidationException::withMessages(['queue' => 'Barber atau layanan tidak tersedia.']);
                }
                // A concurrent retry may have committed while this transaction waited for the barber.
                if (($existing = QueueTicket::query()->where('idempotency_key', $key)->first()) !== null) {
                    return $this->retry($existing, $ownerHash, $fingerprint);
                }

                $now = $this->rules->businessNow();
                $this->rules->assertCanRegister($barber, $service, $now);
                if ($source === QueueSource::Onsite && QrCode::query()->where('code', $data['qr_code'] ?? '')
                    ->where('is_active', true)->lockForUpdate()->first() === null) {
                    throw ValidationException::withMessages(['qr_code' => 'QR lokasi tidak valid atau tidak aktif.']);
                }
                $date = $now->toDateString();
                if (QueueTicket::query()->where('normalized_phone', $phone)->whereDate('queue_date', $date)
                    ->whereIn('status', QueueStatus::activeValues())->exists()) {
                    $this->duplicatePhone();
                }

                $customer = Customer::query()->firstOrCreate(['normalized_phone' => $phone], ['name' => $data['name']]);
                Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $utc = $now->utc();
                DB::table('queue_counters')->insertOrIgnore([
                    'barber_id' => $barber->id, 'queue_date' => $date, 'last_number' => 0,
                    'created_at' => $utc, 'updated_at' => $utc,
                ]);
                $counter = QueueCounter::query()->where('barber_id', $barber->id)->whereDate('queue_date', $date)->lockForUpdate()->firstOrFail();
                $counter->last_number++;
                $counter->save();

                $token = bin2hex(random_bytes(32));
                $status = $source === QueueSource::Online ? QueueStatus::WaitingCheckin : QueueStatus::CheckedIn;
                $ticket = QueueTicket::query()->create([
                    'customer_id' => $customer->id, 'customer_name' => $data['name'], 'normalized_phone' => $phone,
                    'barber_id' => $barber->id, 'service_id' => $service->id, 'queue_date' => $date,
                    'sequence_number' => $counter->last_number,
                    'queue_number' => $barber->queue_prefix.'-'.str_pad((string) $counter->last_number, 3, '0', STR_PAD_LEFT),
                    'source' => $source, 'status' => $status, 'token_hash' => hash('sha256', $token),
                    'owner_hash' => $ownerHash, 'request_fingerprint' => $fingerprint, 'idempotency_key' => $key,
                    'price_snapshot' => $service->price, 'duration_snapshot' => $service->estimated_duration_minutes,
                    'checked_in_at' => $source === QueueSource::Onsite ? $utc : null,
                ]);
                QueueEvent::query()->create([
                    'queue_ticket_id' => $ticket->id, 'event_type' => 'created', 'previous_status' => null,
                    'new_status' => $status, 'created_at' => $utc,
                ]);

                return new CreatedQueueTicket($ticket, $token, true);
            }, 5);
        } catch (QueryException $exception) {
            return $this->recoverUniqueConflict($exception, $key, $ownerHash, $fingerprint, $phone);
        }
    }

    private function recoverUniqueConflict(QueryException $exception, string $key, string $ownerHash, string $fingerprint, string $phone): CreatedQueueTicket
    {
        // A new read after rollback can see the transaction that won the race.
        // Never query inside a failed PostgreSQL transaction.
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            throw $exception;
        }
        $racedTicket = QueueTicket::query()->where('idempotency_key', $key)->first();
        if ($racedTicket !== null) {
            return $this->retry($racedTicket, $ownerHash, $fingerprint);
        }
        if (QueueTicket::query()->where('normalized_phone', $phone)->whereDate('queue_date', $this->rules->businessNow()->toDateString())
            ->whereIn('status', QueueStatus::activeValues())->exists()) {
            $this->duplicatePhone();
        }

        throw $exception;
    }

    private function retry(QueueTicket $ticket, string $ownerHash, string $fingerprint): CreatedQueueTicket
    {
        if (! hash_equals($ticket->owner_hash, $ownerHash)) {
            throw new AccessDeniedHttpException('Permintaan ini tidak dapat diakses dari sesi Anda.');
        }
        if (! hash_equals($ticket->request_fingerprint, $fingerprint)) {
            throw new ConflictHttpException('Kunci permintaan sudah digunakan untuk data yang berbeda.');
        }

        return new CreatedQueueTicket($ticket, null, false);
    }

    private function duplicatePhone(): never
    {
        throw ValidationException::withMessages(['phone' => 'Nomor HP ini sudah memiliki antrean aktif hari ini.']);
    }
}
