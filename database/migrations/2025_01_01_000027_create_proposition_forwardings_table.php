<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Encaminhamento de proposições para comissões.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposition_forwardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('aguardando_recebimento');
            $table->date('deadline')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'status']);
            $table->index('proposition_id');
            $table->index('commission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_forwardings');
    }
};
