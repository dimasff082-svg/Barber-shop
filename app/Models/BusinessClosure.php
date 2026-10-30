<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BusinessClosureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CarbonImmutable $date
 * @property string|null $reason
 */
class BusinessClosure extends Model
{
    /** @use HasFactory<BusinessClosureFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }
}
