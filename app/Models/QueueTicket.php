<?php

namespace App\Models;

use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use Carbon\CarbonImmutable;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property int $customer_id
 * @property int $barber_id
 * @property int $service_id
 * @property string $customer_name
 * @property string $normalized_phone
 * @property CarbonImmutable $queue_date
 * @property int $sequence_number
 * @property string $queue_number
 * @property QueueSource $source
 * @property QueueStatus $status
 * @property string $token_hash
 * @property string $owner_hash
 * @property string $request_fingerprint
 * @property string $idempotency_key
 * @property string $price_snapshot
 * @property int $duration_snapshot
 * @property CarbonImmutable|null $grace_started_at
 * @property CarbonImmutable|null $grace_expires_at
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $called_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $missed_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer $customer
 * @property-read Barber $barber
 * @property-read Service $service
 */
#[Hidden(['token_hash', 'owner_hash', 'request_fingerprint', 'idempotency_key', 'normalized_phone'])]
class QueueTicket extends Model
{
    /** @use HasFactory<QueueTicketFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Queue tickets are historical records and cannot be deleted.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'queue_date' => 'immutable_date',
            'sequence_number' => 'integer',
            'source' => QueueSource::class,
            'status' => QueueStatus::class,
            'price_snapshot' => 'decimal:2',
            'duration_snapshot' => 'integer',
            'grace_started_at' => 'immutable_datetime',
            'grace_expires_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'called_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'missed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Barber, $this> */
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<QueueEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class);
    }
}
