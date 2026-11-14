<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 4 — Votos individuais (votação nominal e secreta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voting_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voting_id')->constrained('votings')->cascadeOnDelete();
            $table->foreignId('councilor_id')->constrained('councilors')->cascadeOnDelete();
            $table->string('vote', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['voting_id', 'councilor_id']);
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voting_records');
    }
};
