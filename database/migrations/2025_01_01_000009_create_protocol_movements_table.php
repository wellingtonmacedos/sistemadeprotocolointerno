<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('to_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 40)->default('aguardando_recebimento');
            $table->text('description')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['protocol_id', 'created_at']);
            $table->index(['institution_id', 'to_sector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_movements');
    }
};
