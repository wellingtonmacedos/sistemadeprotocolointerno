<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Pacote C: vínculo opcional entre providência e divergência.
 *
 * Preserva a independência entre ocorrência, providência e movimentação:
 * uma providência pode existir sem divergência e uma divergência pode existir
 * sem providência. A coluna é nullable para não afetar as providências
 * existentes (FASE 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_actions', function (Blueprint $table) {
            $table->foreignId('divergence_id')->nullable()->after('protocol_movement_id')
                ->constrained('protocol_divergences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('protocol_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('divergence_id');
        });
    }
};
