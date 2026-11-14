<?php

namespace App\Models;

use App\Enums\DeadlineSituation;
use App\Enums\DocumentType;
use App\Enums\ProtocolPriority;
use App\Enums\ProtocolStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Protocol extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'number', 'year', 'formatted_number', 'document_type',
        'subject', 'description', 'sender_name', 'sender_document', 'sender_email',
        'sender_phone', 'destination_sector_id', 'current_sector_id', 'current_commission_id',
        'priority', 'status', 'created_by', 'protocoled_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'priority' => ProtocolPriority::class,
            'status' => ProtocolStatus::class,
            'protocoled_at' => 'datetime',
        ];
    }

    public function destinationSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'destination_sector_id');
    }

    public function currentSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'current_sector_id');
    }

    /** Comissão responsável atual (quando a responsabilidade está em uma comissão). */
    public function currentCommission(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'current_commission_id');
    }

    /** Providências registradas durante a tramitação. */
    public function actions(): HasMany
    {
        return $this->hasMany(ProtocolAction::class)->latest('occurred_at');
    }

    /** Divergências (ocorrências) registradas no protocolo. */
    public function divergences(): HasMany
    {
        return $this->hasMany(ProtocolDivergence::class)->latest();
    }

    /** Retificações de movimentações (histórico append-only). */
    public function movementCorrections(): HasMany
    {
        return $this->hasMany(ProtocolMovementCorrection::class)->latest('created_at');
    }

    /**
     * Responsável atual do protocolo: setor ou comissão.
     * Apenas um dos dois representa a responsabilidade; nunca ambos.
     */
    public function currentResponsibleLabel(): string
    {
        return $this->currentCommission?->name ?? $this->currentSector?->name ?? '—';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProtocolMovement::class)->latest();
    }

    /** Movimentação mais recente (corrente) do protocolo. */
    public function currentMovement(): HasOne
    {
        return $this->hasOne(ProtocolMovement::class)->latestOfMany();
    }

    /** Data-limite da movimentação corrente (se houver). */
    public function currentDueAt(): ?\Illuminate\Support\Carbon
    {
        return $this->currentMovement?->due_at;
    }

    /** Situação do prazo da movimentação corrente. */
    public function deadlineSituation(): DeadlineSituation
    {
        return $this->currentMovement?->deadlineSituation() ?? DeadlineSituation::SEM_PRAZO;
    }

    /**
     * Data de encerramento do protocolo (conclusão/arquivamento), quando houver.
     * Usada para fechar a contagem de permanência do último destino.
     */
    public function closureAt(): ?\Carbon\CarbonInterface
    {
        if (! in_array($this->status, [ProtocolStatus::CONCLUIDO, ProtocolStatus::ARQUIVADO], true)) {
            return null;
        }

        $movements = $this->relationLoaded('movements') ? $this->movements : $this->movements()->get();

        return $movements->sortByDesc('created_at')->first()?->created_at;
    }

    /** Início da permanência no destino atual (recebimento confirmado mais recente). */
    public function currentPermanenceStartedAt(): ?\Carbon\CarbonInterface
    {
        return $this->currentMovement?->received_at;
    }

    /** Duração legível da permanência no destino atual (FASE 2 — §7). */
    public function currentPermanenceDuration(): ?string
    {
        $startedAt = $this->currentPermanenceStartedAt();

        return $startedAt ? ProtocolMovement::humanizeDuration($startedAt) : null;
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProtocolAttachment::class);
    }

    /** Movimentos ainda não confirmados pelo setor de destino. */
    public function pendingMovements(): HasMany
    {
        return $this->hasMany(ProtocolMovement::class)->where('status', 'aguardando_recebimento');
    }
}
