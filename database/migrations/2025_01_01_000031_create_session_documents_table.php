<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 — Documentos anexados às sessões (e, opcionalmente, a um item da pauta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('legislative_sessions')->cascadeOnDelete();
            $table->foreignId('session_agenda_item_id')->nullable()->constrained('session_agenda_items')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('hash_sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('session_id');
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_documents');
    }
};
