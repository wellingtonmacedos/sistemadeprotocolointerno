<?php

namespace App\Models;

use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolActionType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Providência: ação realizada durante a tramitação de um protocolo.
 *
 * Independente da movimentação: pode existir sem movimentação e uma
 * movimentação pode possuir várias providências. Registrar uma providência
 * NÃO cria movimentação nem transfere responsabilidade.
 */
class ProtocolAction extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'protocol_id', 'protocol_movement_id', 'divergence_id',
        'sector_id', 'commission_id', 'user_id',
        'action_type', 'description', 'result', 'occurred_at',
        'requires_follow_up', 'status',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ProtocolActionType::class,
            'status' => ProtocolActionStatus::class,
            'requires_follow_up' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(ProtocolMovement::class, 'protocol_movement_id');
    }

    /** Divergência à qual a providência está relacionada (quando aplicável). */
    public function divergence(): BelongsTo
    {
        return $this->belongsTo(ProtocolDivergence::class, 'divergence_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Local onde a providência foi realizada (comissão ou setor). */
    public function originLabel(): string
    {
        return $this->commission?->name ?? $this->sector?->name ?? '—';
    }
}
