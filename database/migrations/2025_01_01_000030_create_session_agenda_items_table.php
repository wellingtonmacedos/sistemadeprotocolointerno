<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 — Itens da pauta (ordem do dia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('legislative_sessions')->cascadeOnDelete();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('appreciation_type', 40)->default('discussao_e_votacao');
            $table->boolean('will_discuss')->default(true);
            $table->boolean('will_vote')->default(true);
            $table->string('status', 30)->default('pautada');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['session_id', 'proposition_id']);
            $table->index(['session_id', 'position']);
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_agenda_items');
    }
};
