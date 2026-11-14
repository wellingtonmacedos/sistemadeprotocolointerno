<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 4 — Encaminhamentos e situações pós-votação (histórico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_voting_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voting_id')->nullable()->constrained('votings')->nullOnDelete();
            $table->string('status', 40);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'proposition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_voting_actions');
    }
};
