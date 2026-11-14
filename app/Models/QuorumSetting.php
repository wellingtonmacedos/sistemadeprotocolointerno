<?php

namespace App\Models;

use App\Enums\PropositionModality;
use App\Enums\QuorumRule;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class QuorumSetting extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'modality', 'scope_key', 'rule', 'min_presence', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'rule' => QuorumRule::class,
            'min_presence' => 'integer',
        ];
    }

    public static function scopeKeyFor(?int $institutionId, ?string $modality): string
    {
        return ($institutionId ?? 'global').':'.($modality ?: 'padrao');
    }

    /**
     * Regra aplicável: preferencialmente a específica da modalidade;
     * na ausência, a configuração padrão da instituição.
     */
    public static function forInstitution(int $institutionId, ?PropositionModality $modality): ?self
    {
        $query = static::withoutGlobalScope('institution');

        if ($modality) {
            $specific = (clone $query)->where('institution_id', $institutionId)
                ->where('modality', $modality->value)->first();

            if ($specific) {
                return $specific;
            }
        }

        return $query->where('institution_id', $institutionId)->whereNull('modality')->first();
    }

    /** Persiste/atualiza a configuração respeitando o UNIQUE de scope_key. */
    public static function persist(int $institutionId, ?string $modality, array $attributes): self
    {
        $scopeKey = static::scopeKeyFor($institutionId, $modality);

        try {
            return static::updateOrCreate(
                ['scope_key' => $scopeKey],
                ['institution_id' => $institutionId, 'modality' => $modality, ...$attributes]
            );
        } catch (UniqueConstraintViolationException) {
            $setting = static::where('scope_key', $scopeKey)->firstOrFail();
            $setting->update($attributes);

            return $setting;
        }
    }
}
