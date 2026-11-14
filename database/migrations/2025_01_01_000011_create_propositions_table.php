<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('modality', 40);
            $table->unsignedInteger('number')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('official_number', 30)->nullable();
            $table->string('title');
            $table->text('ementa');
            $table->longText('text')->nullable();
            $table->longText('justification')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('author_councilor_id')->nullable()->constrained('councilors')->nullOnDelete();
            $table->string('status', 40)->default('rascunho');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'official_number']);
            $table->unique(['institution_id', 'year', 'modality', 'number']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'modality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propositions');
    }
};
