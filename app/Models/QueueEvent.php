<?php

namespace App\Models;

use App\Enums\QueueStatus;
use Carbon\CarbonImmutable;
use Database\Factories\QueueEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $queue_ticket_id
 * @property int|null $actor_user_id
 * @property string $event_type
 * @property QueueStatus|null $previous_status
 * @property QueueStatus $new_status
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property-read QueueTicket $ticket
 * @property-read User|null $actor
 */
class QueueEvent extends Model
{
    /** @use HasFactory<QueueEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Queue events are append-only.');
        });
        static::deleting(function (): never {
            throw new LogicException('Queue events are append-only.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_status' => QueueStatus::class,
            'new_status' => QueueStatus::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<QueueTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
