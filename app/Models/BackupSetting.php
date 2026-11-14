<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

class BackupSetting extends Model
{
    protected $fillable = [
        'institution_id', 'scope_key', 'enabled', 'frequency', 'run_at',
        'retention_days', 'max_copies', 'destination', 'encrypt',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'encrypt' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** Chave de escopo não nula: "platform" ou "institution:{id}". */
    public static function scopeKeyFor(?int $institutionId): string
    {
        return $institutionId ? 'institution:'.$institutionId : 'platform';
    }

    /**
     * Configuração aplicável ao tenant atual; fallback para a configuração global.
     *
     * O índice UNIQUE em scope_key (não nulo) garante uma única configuração por
     * escopo mesmo sob chamadas concorrentes.
     */
    public static function current(): self
    {
        $institutionId = \App\Support\Tenant::id();
        $scopeKey = static::scopeKeyFor($institutionId);

        try {
            return static::firstOrCreate(
                ['scope_key' => $scopeKey],
                ['institution_id' => $institutionId]
            );
        } catch (UniqueConstraintViolationException) {
            // Corrida concorrente: o registro já foi criado por outra requisição.
            return static::where('scope_key', $scopeKey)->firstOrFail();
        }
    }
}
