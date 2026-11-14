<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Origem da proposição. Permite registrar matérias de autoria externa ao
     * Legislativo (ex.: Projeto de Lei do Executivo), que não possuem vereador
     * autor e são identificadas por `author_external_name`.
     *
     * Migração aditiva e não destrutiva: as proposições existentes recebem a
     * origem "legislativo", mantendo o comportamento atual.
     */
    public function up(): void
    {
        Schema::table('propositions', function (Blueprint $table) {
            $table->string('origin', 30)->default('legislativo');
            $table->string('author_external_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('propositions', function (Blueprint $table) {
            $table->dropColumn(['origin', 'author_external_name']);
        });
    }
};
