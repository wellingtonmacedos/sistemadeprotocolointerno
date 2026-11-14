<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('frequency', 20)->default('daily');
            $table->string('run_at', 5)->default('02:00');
            $table->unsignedSmallInteger('retention_days')->default(15);
            $table->unsignedSmallInteger('max_copies')->default(10);
            $table->string('destination', 40)->default('local');
            $table->boolean('encrypt')->default(false);
            $table->timestamps();

            $table->unique('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
