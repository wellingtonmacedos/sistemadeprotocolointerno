<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setores v2 — recursos adicionais para a tramitação legislativa.
 * Incremental e não destrutiva: nenhum campo existente é alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->string('acronym', 30)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('can_receive')->default(true);
            $table->boolean('can_dispatch')->default(true);
            $table->boolean('can_attach')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn(['acronym', 'display_order', 'can_receive', 'can_dispatch', 'can_attach']);
        });
    }
};
