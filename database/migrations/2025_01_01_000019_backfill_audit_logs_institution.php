<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regulariza o institution_id dos registros de auditoria existentes.
 *
 * Somente vínculos confiáveis (o alvo auditado possui instituição) são preenchidos.
 * Registros sem instituição identificável são preservados e permanecem restritos
 * ao Super Admin.
 */
return new class extends Migration
{
    /** Modelos auditáveis cujo vínculo institucional é confiável. */
    private const SOURCES = [
        'App\\Models\\Protocol' => 'protocols',
        'App\\Models\\Proposition' => 'propositions',
        'App\\Models\\ProtocolMovement' => 'protocol_movements',
        'App\\Models\\ProtocolAttachment' => 'protocol_attachments',
        'App\\Models\\PropositionAttachment' => 'proposition_attachments',
        'App\\Models\\Sector' => 'sectors',
        'App\\Models\\Councilor' => 'councilors',
        'App\\Models\\User' => 'users',
    ];

    public function up(): void
    {
        foreach (self::SOURCES as $type => $table) {
            DB::table('audit_logs')
                ->whereNull('institution_id')
                ->where('auditable_type', $type)
                ->whereNotNull('auditable_id')
                ->chunkById(500, function ($logs) use ($table) {
                    $map = DB::table($table)
                        ->whereIn('id', $logs->pluck('auditable_id')->all())
                        ->pluck('institution_id', 'id');

                    foreach ($logs as $log) {
                        $institutionId = $map[$log->auditable_id] ?? null;

                        if ($institutionId) {
                            DB::table('audit_logs')
                                ->where('id', $log->id)
                                ->update(['institution_id' => $institutionId]);
                        }
                    }
                });
        }

        // Logs cujo próprio alvo é a instituição.
        DB::table('audit_logs')
            ->whereNull('institution_id')
            ->where('auditable_type', 'App\\Models\\Institution')
            ->whereNotNull('auditable_id')
            ->chunkById(500, function ($logs) {
                foreach ($logs as $log) {
                    DB::table('audit_logs')
                        ->where('id', $log->id)
                        ->update(['institution_id' => $log->auditable_id]);
                }
            });
    }

    public function down(): void
    {
        // Não revertemos: preserva os dados históricos já regularizados.
    }
};
