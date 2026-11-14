<?php

namespace App\Support;

use App\Models\Institution;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Guarda a instituição (Câmara) autenticada na requisição atual.
 * Responsável pelo isolamento lógico multi-Câmaras.
 */
class Tenant
{
    protected static ?Institution $institution = null;

    protected static bool $bypassed = false;

    public static function set(?Institution $institution): void
    {
        static::$institution = $institution;
    }

    public static function get(): ?Institution
    {
        return static::$institution;
    }

    public static function id(): ?int
    {
        return static::$institution?->id;
    }

    public static function check(): bool
    {
        return static::$institution !== null;
    }

    /** Ativa/desativa o filtro automático de instituição. */
    public static function bypass(bool $value = true): void
    {
        static::$bypassed = $value;
    }

    public static function isBypassed(): bool
    {
        return static::$bypassed;
    }

    /** Executa um callback sem o isolamento automático. */
    public static function withoutScope(callable $callback): mixed
    {
        $previous = static::$bypassed;
        static::$bypassed = true;

        try {
            return $callback();
        } finally {
            static::$bypassed = $previous;
        }
    }

    /**
     * Regra de validação "exists" limitada à instituição ativa.
     *
     * A validação nativa de "exists" usa o query builder e ignora os global
     * scopes do Eloquent; por isso restringimos explicitamente por
     * institution_id, evitando que um usuário referencie registros de outra
     * Câmara manipulando IDs (ex.: setor ou vereador de outra instituição).
     * Para o SUPER ADMIN (sem instituição ativa) nenhum filtro é aplicado.
     */
    public static function scopedExists(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        if (static::id() !== null) {
            $rule->where('institution_id', static::id());
        }

        return $rule;
    }
}
