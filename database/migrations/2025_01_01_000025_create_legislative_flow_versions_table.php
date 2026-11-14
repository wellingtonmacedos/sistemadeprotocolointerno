<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versionamento dos fluxos legislativos.
 * Cada versão publicada é imutável; proposições em andamento preservam
 * a referência à versão utilizada no momento de sua instauração.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legislative_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            // rascunho | publicado | arquivado
            $table->string('status', 20)->default('rascunho');
            $table->boolean('is_current')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['legislative_flow_id', 'version']);
            $table->index(['institution_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_flow_versions');
    }
};
