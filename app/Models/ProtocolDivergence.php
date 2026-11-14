<?php

namespace App\Models;

use App\Enums\DivergenceStatus;
use App\Enums\DivergenceType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Divergência: ocorrência registrada sobre um protocolo, durante ou após o
 * recebimento.
 *
 * DIVERGÊNCIA NÃO É MOVIMENTAÇÃO: não cria movimentação, não marca recebimento
 * e não altera a responsabilidade atual do protocolo. É uma ocorrência que
 * precisa ser acompanhada até sua resolução ou encerramento.
 */
class ProtocolDivergence extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'protocol_id', 'protocol_movement_id',
        'sector_id', 'commission_id', 'reported_by',
        'type', 'description', 'status', 'resolution_notes', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DivergenceType::class,
            'status' => DivergenceStatus::class,
            'resolved_at' => 'datetime',
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

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** Providências registradas para tratar esta ocorrência. */
    public function actions(): HasMany
    {
        return $this->hasMany(ProtocolAction::class, 'divergence_id');
    }

    /** A ocorrência ainda está em acompanhamento. */
    public function isOpen(): bool
    {
        return ! $this->status->isFinal();
    }

    /** Local responsável pela ocorrência (setor ou comissão). */
    public function responsibleLabel(): string
    {
        return $this->commission?->name ?? $this->sector?->name ?? '—';
    }
}
