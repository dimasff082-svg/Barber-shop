<?php

namespace App\Models;

use Database\Factories\BarberScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barber_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 * @property bool $is_active
 * @property-read Barber $barber
 */
class BarberSchedule extends Model
{
    /** @use HasFactory<BarberScheduleFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Barber, $this> */
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
