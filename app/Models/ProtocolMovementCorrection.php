<?php

namespace App\Models;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\MovementType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Retificação de uma movimentação histórica (FASE 2).
 *
 * RETIFICAÇÃO NÃO É EXCLUSÃO: o registro original permanece preservado. Esta
 * entidade é um histórico append-only que guarda os valores anteriores
 * (`original_data`), os valores aplicados (`corrected_data`), o motivo, o
 * responsável e a data/hora da retificação.
 */
class ProtocolMovementCorrection extends Model
{
    use BelongsToInstitution;

    /** Histórico imutável: apenas o momento de criação é registrado. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'institution_id', 'protocol_id', 'protocol_movement_id', 'user_id',
        'reason', 'original_data', 'corrected_data', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'original_data' => 'array',
            'corrected_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(ProtocolMovement::class, 'protocol_movement_id');
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Rótulo legível de um campo retificável. */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'description' => 'Descrição',
            'type' => 'Tipo de encaminhamento',
            'deadline' => 'Prazo (dias)',
            'due_at' => 'Data-limite',
            'deadline_type' => 'Tipo do prazo',
            'deadline_mode' => 'Modo de contagem',
            'deadline_notes' => 'Observação do prazo',
            'to_sector_id' => 'Setor de destino',
            'to_commission_id' => 'Comissão de destino',
            'destination_type' => 'Tipo de destino',
            default => $field,
        };
    }

    /**
     * Linhas comparativas (campo · valor original · valor corrigido) para exibição.
     *
     * @return array<int,array{label:string,original:string,corrected:string}>
     */
    public function comparisonRows(): array
    {
        $rows = [];

        foreach ($this->original_data ?? [] as $field => $original) {
            $rows[] = [
                'label' => self::fieldLabel($field),
                'original' => $this->humanize($field, $original),
                'corrected' => $this->humanize($field, $this->corrected_data[$field] ?? null),
            ];
        }

        return $rows;
    }

    /** Converte um valor bruto em texto legível para o histórico de retificação. */
    private function humanize(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'type' => MovementType::tryFrom((string) $value)?->label() ?? (string) $value,
            'deadline_type' => DeadlineType::tryFrom((string) $value)?->label() ?? (string) $value,
            'deadline_mode' => DeadlineMode::tryFrom((string) $value)?->label() ?? (string) $value,
            'destination_type' => DestinationType::tryFrom((string) $value)?->label() ?? (string) $value,
            'due_at' => Carbon::parse($value)->format('d/m/Y H:i'),
            'to_sector_id' => Sector::withoutGlobalScopes()
                ->whereKey($value)->where('institution_id', $this->institution_id)->value('name') ?? ('Setor #'.$value),
            'to_commission_id' => Commission::withoutGlobalScopes()
                ->whereKey($value)->where('institution_id', $this->institution_id)->value('name') ?? ('Comissão #'.$value),
            default => (string) $value,
        };
    }
}
