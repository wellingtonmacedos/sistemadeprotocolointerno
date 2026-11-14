<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 — Sessões legislativas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('type', 30)->default('ordinaria');
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('president_councilor_id')->nullable()->constrained('councilors')->nullOnDelete();
            $table->string('status', 30)->default('agendada');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'date']);
            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_sessions');
    }
};
