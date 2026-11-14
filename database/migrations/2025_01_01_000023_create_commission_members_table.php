<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composição histórica das comissões.
 * Cada registro representa um mandato (cargo) de um vereador na comissão.
 * A composição vigente é composta pelos registros sem data de término.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('councilor_id')->constrained()->cascadeOnDelete();
            // presidente | vice_presidente | membro | relator
            $table->string('role', 30)->default('membro');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commission_id', 'end_date']);
            $table->index(['institution_id', 'councilor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_members');
    }
};
