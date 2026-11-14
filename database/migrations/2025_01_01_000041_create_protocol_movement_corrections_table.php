<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retificações de movimentações (FASE 2).
 *
 * RETIFICAÇÃO NÃO É EXCLUSÃO: cada retificação registra o motivo, o responsável,
 * a data/hora e uma cópia dos valores anteriores (`original_data`) e aplicados
 * (`corrected_data`). O registro original permanece preservado e consultável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_movement_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_movement_id')->constrained('protocol_movements')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('original_data');
            $table->json('corrected_data');
            $table->timestamp('created_at')->nullable();

            $table->index(['institution_id', 'created_at']);
            $table->index('protocol_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_movement_corrections');
    }
};
