<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Pacote C: ocorrências de divergência.
 *
 * Uma divergência é uma ocorrência que precisa ser acompanhada, mas que não
 * impede o recebimento nem transfere responsabilidade. Estrutura puramente
 * aditiva (nenhuma coluna existente é alterada) e compatível com SQLite e MySQL.
 *
 * Registros antigos simplesmente não possuem divergências: nenhuma ocorrência
 * é presumida ou inventada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_divergences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_movement_id')->nullable()->constrained('protocol_movements')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->text('description');
            $table->string('status', 30)->default('aberta');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['protocol_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_divergences');
    }
};
