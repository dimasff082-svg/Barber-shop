<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\QueueCounterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barber_id
 * @property CarbonImmutable $queue_date
 * @property int $last_number
 * @property-read Barber $barber
 */
class QueueCounter extends Model
{
    /** @use HasFactory<QueueCounterFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['queue_date' => 'immutable_date', 'last_number' => 'integer'];
    }

    /** @return BelongsTo<Barber, $this> */
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
