<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoria de operações sensíveis.
 */
class AuditLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
    ): AuditLog {
        $user = auth()->user();

        return AuditLog::create([
            'institution_id' => $subject?->institution_id ?? Tenant::id(),
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 250),
            'description' => $description,
        ]);
    }
}
