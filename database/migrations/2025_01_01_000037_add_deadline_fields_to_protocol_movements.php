<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Pacote A: prazos nas movimentações.
 *
 * Campos incrementais e opcionais para preservar movimentações existentes.
 * Não há simulação de calendário/feriados nesta fase; a origem e o modo de
 * contagem ficam registrados para evolução futura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_movements', function (Blueprint $table) {
            $table->unsignedSmallInteger('deadline')->nullable()->after('description');
            $table->timestamp('due_at')->nullable()->after('deadline');
            $table->string('deadline_type', 20)->nullable()->after('due_at');
            $table->string('deadline_source', 30)->nullable()->after('deadline_type');
            $table->string('deadline_mode', 20)->nullable()->after('deadline_source');
            $table->text('deadline_notes')->nullable()->after('deadline_mode');

            $table->index(['institution_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('protocol_movements', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'due_at']);
            $table->dropColumn([
                'deadline', 'due_at', 'deadline_type',
                'deadline_source', 'deadline_mode', 'deadline_notes',
            ]);
        });
    }
};
