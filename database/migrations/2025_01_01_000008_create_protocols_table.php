<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedSmallInteger('year');
            $table->string('formatted_number', 20);
            $table->string('document_type', 40);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('sender_name');
            $table->string('sender_document', 20)->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_phone', 30)->nullable();
            $table->foreignId('destination_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('current_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 40)->default('aguardando_recebimento');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('protocoled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'year', 'number']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'destination_sector_id']);
            $table->index(['institution_id', 'current_sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocols');
    }
};
