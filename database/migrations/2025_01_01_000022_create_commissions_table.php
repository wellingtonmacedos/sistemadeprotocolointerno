<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Comissões Legislativas (independente dos setores administrativos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('acronym', 30);
            $table->string('description')->nullable();
            $table->text('competences')->nullable();
            // permanente | temporaria
            $table->string('type', 20)->default('permanente');
            // modalidades de proposição que a comissão pode analisar
            $table->json('allowed_modalities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'acronym']);
            $table->index(['institution_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
