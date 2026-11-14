<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona uma chave de escopo não nula em backup_settings, permitindo
 * restrição UNIQUE efetiva (o índice em institution_id não restringe NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->string('scope_key', 60)->default('platform');
        });

        // Backfill: escopo da plataforma e escopo por Câmara.
        DB::table('backup_settings')->whereNull('institution_id')->update(['scope_key' => 'platform']);

        foreach (DB::table('backup_settings')->whereNotNull('institution_id')->get() as $row) {
            DB::table('backup_settings')->where('id', $row->id)->update([
                'scope_key' => 'institution:'.$row->institution_id,
            ]);
        }

        // Remove duplicidades pré-existentes, mantendo o registro mais antigo.
        $duplicates = DB::table('backup_settings')
            ->select('scope_key', DB::raw('MIN(id) as keep_id'))
            ->groupBy('scope_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('backup_settings')
                ->where('scope_key', $duplicate->scope_key)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('backup_settings', function (Blueprint $table) {
            $table->unique('scope_key');
        });
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropUnique(['scope_key']);
            $table->dropColumn('scope_key');
        });
    }
};
