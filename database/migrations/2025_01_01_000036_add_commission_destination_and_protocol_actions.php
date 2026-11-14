<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 — Estrutura central de tramitação documental.
 *
 * Incremental e não destrutiva:
 *  - Comissões passam a ser destinos de tramitação (duas FKs explícitas + tipo).
 *  - Protocolo passa a poder ter responsabilidade atual em setor OU comissão.
 *  - Nova entidade de PROVIDÊNCIAS (ações realizadas durante a tramitação).
 *
 * Todas as colunas acrescentadas são nullable para preservar os registros
 * existentes. Compatível com SQLite e MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_movements', function (Blueprint $table) {
            $table->foreignId('from_commission_id')->nullable()->after('from_sector_id')->constrained('commissions')->nullOnDelete();
            $table->foreignId('to_commission_id')->nullable()->after('to_sector_id')->constrained('commissions')->nullOnDelete();
            // sector | commission — consistente com as chaves preenchidas.
            $table->string('destination_type', 20)->nullable()->after('to_commission_id');

            $table->index(['institution_id', 'to_commission_id', 'status']);
        });

        Schema::table('protocols', function (Blueprint $table) {
            // Responsabilidade atual: setor OU comissão (nunca ambos).
            $table->foreignId('current_commission_id')->nullable()->after('current_sector_id')->constrained('commissions')->nullOnDelete();

            $table->index(['institution_id', 'current_commission_id']);
        });

        Schema::create('protocol_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_movement_id')->nullable()->constrained('protocol_movements')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 40);
            $table->text('description');
            $table->text('result')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->boolean('requires_follow_up')->default(false);
            $table->string('status', 30)->default('registrada');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['protocol_id', 'occurred_at']);
            $table->index(['institution_id', 'action_type']);
            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_actions');

        Schema::table('protocols', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'current_commission_id']);
            $table->dropConstrainedForeignId('current_commission_id');
        });

        Schema::table('protocol_movements', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'to_commission_id', 'status']);
            $table->dropConstrainedForeignId('to_commission_id');
            $table->dropConstrainedForeignId('from_commission_id');
            $table->dropColumn('destination_type');
        });
    }
};
