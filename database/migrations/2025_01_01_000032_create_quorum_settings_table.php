<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 4 — Regras de quórum configuráveis por instituição e modalidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quorum_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('modality', 60)->nullable();
            $table->string('scope_key', 120);
            $table->string('rule', 40)->default('maioria_simples');
            $table->unsignedInteger('min_presence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('scope_key');
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quorum_settings');
    }
};
