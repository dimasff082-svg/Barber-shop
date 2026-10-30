<?php

namespace App\Services;

use App\Models\StaffAuditEntry;
use App\Models\User;

class StaffAudit
{
    /** @param array<string, mixed> $metadata */
    public function record(User $actor, string $action, string $targetType, string|int|null $targetId, array $metadata = []): void
    {
        StaffAuditEntry::query()->create([
            'actor_user_id' => $actor->id, 'action' => $action, 'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'metadata' => $this->redact($metadata),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<array-key, mixed>
     */
    private function redact(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (is_string($key) && preg_match('/password|token|secret|authorization|cookie|credential|idempotency/i', $key)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = $this->redact($value);
            } elseif (is_string($value) || is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 2000) : $value;
            }
        }

        return $safe;
    }
}
