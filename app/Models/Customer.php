<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $normalized_phone
 */
#[Hidden(['normalized_phone'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return HasMany<QueueTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}
