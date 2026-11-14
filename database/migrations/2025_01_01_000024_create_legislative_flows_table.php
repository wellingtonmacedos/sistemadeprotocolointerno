<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fluxos legislativos configuráveis por instituição e modalidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // valor de PropositionModality (PL, PR, PDL, IND, MOC, REQ...)
            $table->string('modality', 40);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'modality']);
            $table->index(['institution_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_flows');
    }
};
