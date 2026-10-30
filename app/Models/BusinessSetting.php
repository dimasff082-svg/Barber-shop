<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property mixed $value
 */
class BusinessSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
