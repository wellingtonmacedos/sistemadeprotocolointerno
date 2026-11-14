<?php

namespace App\Models;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineSituation;
use App\Enums\DeadlineSource;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\RefusalReason;
use App\Models\Concerns\BelongsToInstitution;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProtocolMovement extends Model
{
    use BelongsToInstitution;

    /** Janela, em dias, para considerar o prazo como "próximo do vencimento". */
    public const NEAR_DUE_DAYS = 2;

    protected $fillable = [
        'institution_id', 'protocol_id',
        'from_sector_id', 'from_commission_id',
        'to_sector_id', 'to_commission_id', 'destination_type',
        'from_user_id', 'to_user_id', 'received_by', 'type', 'status',
        'description', 'received_at',
        'deadline', 'due_at', 'deadline_type', 'deadline_source',
        'deadline_mode', 'deadline_notes',
        'refusal_reason', 'refusal_notes', 'refused_at', 'refused_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'status' => MovementStatus::class,
            'destination_type' => DestinationType::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'deadline_type' => DeadlineType::class,
            'deadline_source' => DeadlineSource::class,
            'deadline_mode' => DeadlineMode::class,
            'refusal_reason' => RefusalReason::class,
            'refused_at' => 'datetime',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function fromSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'from_sector_id');
    }

    /** Comissão de origem, quando a movimentação partiu de uma comissão. */
    public function fromCommission(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'from_commission_id');
    }

    public function toSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'to_sector_id');
    }

    /** Comissão de destino, quando o destino é uma comissão. */
    public function toCommission(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'to_commission_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Usuário que recusou formalmente o recebimento. */
    public function refusedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refused_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProtocolAttachment::class);
    }

    /** Providências registradas em uma movimentação (nenhuma, uma ou várias). */
    public function actions(): HasMany
    {
        return $this->hasMany(ProtocolAction::class);
    }

    /** Divergências registradas sobre esta movimentação (ocorrências). */
    public function divergences(): HasMany
    {
        return $this->hasMany(ProtocolDivergence::class);
    }

    /** Retificações aplicadas a esta movimentação (histórico append-only). */
    public function corrections(): HasMany
    {
        return $this->hasMany(ProtocolMovementCorrection::class, 'protocol_movement_id');
    }

    public function isPending(): bool
    {
        return $this->status === MovementStatus::AGUARDANDO_RECEBIMENTO;
    }

    /** O recebimento foi formalmente recusado pelo destinatário. */
    public function isRefused(): bool
    {
        return $this->status === MovementStatus::RECUSADO || $this->refused_at !== null;
    }

    /** A movimentação possui prazo definido (data-limite). */
    public function hasDeadline(): bool
    {
        return $this->due_at !== null;
    }

    /** A movimentação já foi concluída (recebida). */
    public function isCompleted(): bool
    {
        return $this->received_at !== null || $this->status === MovementStatus::RECEBIDO;
    }

    /** Prazo vencido e ainda pendente de recebimento. */
    public function isOverdue(): bool
    {
        return $this->deadlineSituation() === DeadlineSituation::VENCIDO;
    }

    /** Prazo dentro da janela de proximidade do vencimento. */
    public function isNearDue(): bool
    {
        if (! $this->due_at || $this->isCompleted()) {
            return false;
        }

        $remaining = now()->diffInDays($this->due_at, false);

        return $remaining >= 0 && $remaining <= self::NEAR_DUE_DAYS;
    }

    /**
     * Situação do prazo considerando conclusão e vencimento.
     * Sem data-limite, a movimentação é classificada como "sem prazo".
     */
    public function deadlineSituation(): DeadlineSituation
    {
        if (! $this->due_at) {
            return DeadlineSituation::SEM_PRAZO;
        }

        if ($this->isCompleted()) {
            $concludedAt = $this->received_at ?? now();

            return $concludedAt->lessThanOrEqualTo($this->due_at)
                ? DeadlineSituation::CONCLUIDO_NO_PRAZO
                : DeadlineSituation::CONCLUIDO_APOS_PRAZO;
        }

        if (now()->greaterThan($this->due_at)) {
            return DeadlineSituation::VENCIDO;
        }

        return $this->isNearDue()
            ? DeadlineSituation::PROXIMO_VENCIMENTO
            : DeadlineSituation::NO_PRAZO;
    }

    /** Descrição textual do prazo (ex.: "5 dias corridos · Administrativo"). */
    public function deadlineLabel(): string
    {
        if (! $this->hasDeadline()) {
            return 'Sem prazo';
        }

        $parts = [];
        $parts[] = $this->deadline ? "{$this->deadline} " . strtolower($this->deadline_mode?->label() ?? 'dias') : 'Prazo';

        if ($this->deadline_type) {
            $parts[] = $this->deadline_type->label();
        }

        return implode(' · ', $parts);
    }

    /** Descrição textual do motivo da recusa (quando houver). */
    public function refusalLabel(): string
    {
        return $this->refusal_reason?->label() ?? '—';
    }

    /**
     * Permanência no destino (FASE 2 — §7).
     *
     * Só existe permanência após o recebimento confirmado: uma movimentação
     * aguardando recebimento ou recusada NÃO inicia contagem no destino.
     */
    public function hasPermanence(): bool
    {
        return $this->received_at !== null;
    }

    /** Momento em que a permanência no destino começou (recebimento confirmado). */
    public function permanenceStartedAt(): ?CarbonInterface
    {
        return $this->received_at;
    }

    /**
     * Momento em que a permanência no destino terminou: a próxima transferência
     * efetiva (mudança de destino), o encerramento do protocolo ou o momento
     * atual. Sem recebimento confirmado, não há permanência.
     */
    public function permanenceEndedAt(): ?CarbonInterface
    {
        if ($this->received_at === null) {
            return null;
        }

        return $this->followingTransfer()?->created_at
            ?? $this->protocol?->closureAt()
            ?? now();
    }

    /** Duração legível da permanência no destino (ex.: "2 d e 3 h"). */
    public function permanenceDuration(): ?string
    {
        return $this->hasPermanence()
            ? self::humanizeDuration($this->permanenceStartedAt(), $this->permanenceEndedAt())
            : null;
    }

    /**
     * Próxima transferência efetiva: movimento posterior que altera o destino.
     * Andamentos no mesmo destino (despacho, parecer...) não encerram a
     * permanência do documento no setor ou comissão.
     */
    public function followingTransfer(): ?self
    {
        $siblings = $this->protocol?->relationLoaded('movements')
            ? $this->protocol->movements
            : static::query()->where('protocol_id', $this->protocol_id)->orderBy('id')->get();

        return collect($siblings)
            ->sortBy('id')
            ->first(fn (self $m) => $m->id > $this->id
                && ($m->to_sector_id !== $this->to_sector_id
                    || $m->to_commission_id !== $this->to_commission_id));
    }

    /** Duração legível entre dois instantes (ex.: "45 min", "3 h", "2 d e 5 h"). */
    public static function humanizeDuration(CarbonInterface|string|null $from, CarbonInterface|string|null $to = null): ?string
    {
        if ($from === null) {
            return null;
        }

        $start = Carbon::parse($from);
        $end = $to !== null ? Carbon::parse($to) : Carbon::now();

        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        $minutes = (int) $start->diffInMinutes($end);

        if ($minutes < 1) {
            return 'menos de 1 min';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $days = intdiv($hours, 24);
        $hours %= 24;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' d';
        }

        if ($hours > 0 || $days === 0) {
            $parts[] = $hours.' h';
        }

        return implode(' e ', $parts);
    }

    /**
     * Tipo de destino da movimentação, derivado das chaves preenchidas.
     * Compatível com movimentações antigas (apenas setor) e novas (comissão).
     */
    public function destinationType(): ?DestinationType
    {
        if ($this->to_commission_id) {
            return DestinationType::COMMISSION;
        }

        if ($this->to_sector_id) {
            return DestinationType::SECTOR;
        }

        return $this->destination_type;
    }

    /** Identificação uniforme do destinatário (setor ou comissão). */
    public function destinationLabel(): string
    {
        return $this->toCommission?->name ?? $this->toSector?->name ?? '—';
    }

    /** Identificação uniforme da origem (setor ou comissão). */
    public function originLabel(): string
    {
        return $this->fromCommission?->name ?? $this->fromSector?->name ?? 'Origem externa';
    }
}
