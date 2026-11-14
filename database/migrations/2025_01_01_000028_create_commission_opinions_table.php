<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Pareceres de comissão sobre proposições.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_opinions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forwarding_id')->nullable()->constrained('proposition_forwardings')->nullOnDelete();
            $table->foreignId('rapporteur_councilor_id')->nullable()->constrained('councilors')->nullOnDelete();
            $table->string('result', 30);
            $table->longText('text');
            $table->date('opinion_date')->nullable();
            $table->foreignId('proposition_attachment_id')->nullable()->constrained('proposition_attachments')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'result']);
            $table->index('proposition_id');
            $table->index('commission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_opinions');
    }
};
