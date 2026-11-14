<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas de cada versão do fluxo legislativo.
 * Etapa (configuração) é distinta de movimentação (execução).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legislative_flow_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            // recebimento | analise | despacho | juridico | comissao | consolidacao |
            // pauta | plenario | votacao | resultado | redacao_final | sancao |
            // publicacao | conclusao | outro
            $table->string('step_type', 40)->default('analise');
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('deadline_days')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('can_skip')->default(false);
            $table->boolean('requires_document')->default(false);
            $table->text('advance_condition')->nullable();
            $table->boolean('allows_return')->default(false);
            $table->boolean('is_conclusive')->default(false);
            $table->string('conclusion_status', 40)->nullable();
            $table->timestamps();

            $table->index(['legislative_flow_version_id', 'position']);
            $table->index(['institution_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_flow_steps');
    }
};
