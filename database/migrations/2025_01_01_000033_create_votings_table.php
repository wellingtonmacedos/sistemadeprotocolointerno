<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 4 — Registro de votações vinculadas a sessões e proposições.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('legislative_sessions')->cascadeOnDelete();
            $table->foreignId('session_agenda_item_id')->nullable()->constrained('session_agenda_items')->nullOnDelete();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('turno')->default(1);
            $table->string('type', 20)->default('nominal');
            $table->string('result', 30)->nullable();
            $table->string('quorum_rule', 40)->nullable();
            $table->unsignedInteger('quorum_min_presence')->nullable();
            $table->unsignedInteger('votes_favor')->default(0);
            $table->unsignedInteger('votes_against')->default(0);
            $table->unsignedInteger('votes_abstention')->default(0);
            $table->unsignedInteger('votes_impeded')->default(0);
            $table->unsignedInteger('votes_absent')->default(0);
            $table->unsignedInteger('present_count')->default(0);
            $table->timestamp('held_at')->nullable();
            $table->timestamp('result_registered_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['session_agenda_item_id', 'turno']);
            $table->index(['institution_id', 'session_id']);
            $table->index('proposition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votings');
    }
};
