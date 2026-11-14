<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposition_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('councilor_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['proposition_id', 'councilor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_authors');
    }
};
