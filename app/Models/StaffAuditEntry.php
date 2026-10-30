<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $actor_user_id
 * @property string $action
 * @property string $target_type
 * @property string|null $target_id
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $created_at
 * @property-read User $actor
 */
class StaffAuditEntry extends Model
{
    protected $table = 'staff_audits';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Staff audit is append-only.'));
        static::deleting(fn () => throw new LogicException('Staff audit is append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
