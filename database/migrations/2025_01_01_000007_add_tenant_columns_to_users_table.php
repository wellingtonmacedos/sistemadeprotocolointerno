<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('institution_id')->constrained('roles')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->after('role_id')->constrained('sectors')->nullOnDelete();
            $table->foreignId('councilor_id')->nullable()->after('sector_id')->constrained('councilors')->nullOnDelete();
            $table->string('document', 20)->nullable()->after('email');
            $table->string('phone', 30)->nullable()->after('document');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->index(['institution_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('councilor_id');
            $table->dropConstrainedForeignId('sector_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn(['document', 'phone', 'is_active', 'last_login_at', 'deleted_at']);
        });
    }
};
