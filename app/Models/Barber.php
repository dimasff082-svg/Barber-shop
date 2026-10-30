<?php

namespace App\Models;

use App\Enums\BarberAvailability;
use Database\Factories\BarberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $photo
 * @property string|null $bio
 * @property string|null $specialization
 * @property string $queue_prefix
 * @property BarberAvailability $availability_status
 * @property bool $is_active
 * @property bool $accepts_queue
 * @property int|null $daily_capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'name', 'slug', 'photo', 'bio', 'specialization', 'queue_prefix', 'availability_status', 'is_active', 'accepts_queue', 'daily_capacity'])]
class Barber extends Model
{
    /** @use HasFactory<BarberFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'availability_status' => BarberAvailability::class,
            'is_active' => 'boolean',
            'accepts_queue' => 'boolean',
            'daily_capacity' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<BarberSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(BarberSchedule::class);
    }

    /** @return HasMany<QueueTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /** @return HasMany<QueueCounter, $this> */
    public function counters(): HasMany
    {
        return $this->hasMany(QueueCounter::class);
    }
}
