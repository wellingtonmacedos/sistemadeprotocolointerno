<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Pacote B: recusa de recebimento.
 *
 * Campos incrementais e opcionais para preservar as movimentações existentes.
 * A recusa é uma ocorrência formal sobre a movimentação original, que nunca é
 * apagada nem convertida em recebimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_movements', function (Blueprint $table) {
            $table->string('refusal_reason', 30)->nullable()->after('deadline_notes');
            $table->text('refusal_notes')->nullable()->after('refusal_reason');
            $table->timestamp('refused_at')->nullable()->after('refusal_notes');
            $table->foreignId('refused_by')->nullable()->after('refused_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['institution_id', 'refused_at']);
        });
    }

    public function down(): void
    {
        Schema::table('protocol_movements', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'refused_at']);
            $table->dropConstrainedForeignId('refused_by');
            $table->dropColumn(['refusal_reason', 'refusal_notes', 'refused_at']);
        });
    }
};
