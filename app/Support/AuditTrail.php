<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditTrail
{
    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    public static function record(Model $record, ?int $actorId, string $event, ?array $oldValues, ?array $newValues): AuditLog
    {
        return AuditLog::create([
            'shop_id' => $record->getAttribute('shop_id'),
            'user_id' => $actorId,
            'event' => $event,
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
