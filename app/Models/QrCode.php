<?php

namespace App\Models;

use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string|null $label
 * @property bool $is_active
 */
class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
