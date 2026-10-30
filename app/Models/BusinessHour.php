<?php

namespace App\Models;

use Database\Factories\BusinessHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $weekday
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property bool $is_closed
 */
class BusinessHour extends Model
{
    /** @use HasFactory<BusinessHourFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_closed' => 'boolean'];
    }
}
