<?php

namespace App\Services;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineSource;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\DivergenceStatus;
use App\Enums\DivergenceType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\ProtocolStatus;
use App\Enums\RefusalReason;
use App\Models\NumberingSequence;
use App\Models\Protocol;
use App\Models\ProtocolDivergence;
use App\Models\ProtocolMovement;
use App\Models\ProtocolMovementCorrection;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras de negócio do módulo de protocolos e tramitação.
 * Encaminhamento não significa recebimento: todo recebimento é confirmado pelo setor.
 */
class ProtocolService
{
    /** Campos retificáveis de uma movimentação (whitelist). */
    private const CORRECTABLE_FIELDS = [
        'description', 'type',
        'deadline', 'due_at', 'deadline_type', 'deadline_mode', 'deadline_notes',
        'to_sector_id', 'to_commission_id', 'destination_type',
    ];

    /** Campos cuja alteração muda o destino e exige autorização elevada. */
    private const DESTINATION_FIELDS = ['to_sector_id', 'to_commission_id', 'destination_type'];

    public function __construct(private NumberingService $numbering) {}

    public function create(array $data, User $user): Protocol
    {
        return DB::transaction(function () use ($data, $user) {
            $institutionId = $data['institution_id'] ?? Tenant::id();
            $year = (int) ($data['year'] ?? now()->year);

            $number = $this->numbering->next(
                $institutionId,
                NumberingSequence::SCOPE_PROTOCOL,
                '',
                $year
            );

            $protocol = Protocol::create([
                ...$data,
                'institution_id' => $institutionId,
                'number' => $number,
                'year' => $year,
                'formatted_number' => $this->numbering->format($number, $year),
                'status' => ProtocolStatus::AGUARDANDO_RECEBIMENTO,
                'current_sector_id' => null,
                'created_by' => $user->id,
                'protocoled_at' => now(),
            ]);

            if (! empty($data['destination_sector_id'])) {
                $this->forward(
                    $protocol,
                    $user,
                    DestinationType::SECTOR,
                    (int) $data['destination_sector_id'],
                    $data['description'] ?? 'Encaminhamento inicial ao setor de destino.',
                    MovementType::RECEBIMENTO_INICIAL,
                    null,
                    isset($data['deadline_days']) ? (int) $data['deadline_days'] : null,
                    $data['deadline_type'] ?? null,
                    $data['deadline_mode'] ?? null,
                    $data['deadline_notes'] ?? null,
                );
            }

            AuditLogger::log('protocol.created', $protocol, [], $protocol->only([
                'formatted_number', 'subject', 'status',
            ]), 'Protocolo '.$protocol->formatted_number.' cadastrado.');

            return $protocol->refresh();
        });
    }

    /**
     * Encaminha o protocolo a um destino institucional (setor ou comissão).
     * Gera um movimento pendente de recebimento. A responsabilidade NÃO é
     * transferida aqui: só é atribuída após a confirmação de recebimento.
     *
     * O prazo é opcional; quando informado, a data-limite é calculada a partir
     * do momento do encaminhamento conforme o modo de contagem (corridos/úteis).
     */
    public function forward(
        Protocol $protocol,
        User $user,
        DestinationType $destinationType,
        int $destinationId,
        ?string $description = null,
        MovementType $type = MovementType::ENCAMINHAMENTO,
        ?int $toUserId = null,
        ?int $deadlineDays = null,
        DeadlineType|string|null $deadlineType = null,
        DeadlineMode|string|null $deadlineMode = null,
        ?string $deadlineNotes = null,
    ): ProtocolMovement {
        return DB::transaction(function () use ($protocol, $user, $destinationType, $destinationId, $description, $type, $toUserId, $deadlineDays, $deadlineType, $deadlineMode, $deadlineNotes) {
            $isSector = $destinationType === DestinationType::SECTOR;

            $deadlineType = $deadlineType instanceof DeadlineType
                ? $deadlineType
                : ($deadlineType ? DeadlineType::tryFrom($deadlineType) : null);

            $deadlineMode = $deadlineMode instanceof DeadlineMode
                ? $deadlineMode
                : ($deadlineMode ? DeadlineMode::tryFrom($deadlineMode) : null);
            $deadlineMode ??= DeadlineMode::CORRIDOS;

            $hasDeadline = $deadlineDays !== null && $deadlineDays > 0;
            $dueAt = $hasDeadline ? $deadlineMode->addTo(now(), $deadlineDays) : null;

            $movement = ProtocolMovement::create([
                'institution_id' => $protocol->institution_id,
                'protocol_id' => $protocol->id,
                'from_sector_id' => $protocol->current_sector_id,
                'from_commission_id' => $protocol->current_commission_id,
                'to_sector_id' => $isSector ? $destinationId : null,
                'to_commission_id' => $isSector ? null : $destinationId,
                'destination_type' => $destinationType,
                'from_user_id' => $user->id,
                'to_user_id' => $toUserId,
                'type' => $type,
                'status' => MovementStatus::AGUARDANDO_RECEBIMENTO,
                'description' => $description,
                'deadline' => $hasDeadline ? $deadlineDays : null,
                'due_at' => $dueAt,
                'deadline_type' => $hasDeadline ? $deadlineType : null,
                'deadline_source' => $hasDeadline ? DeadlineSource::MANUAL : null,
                'deadline_mode' => $hasDeadline ? $deadlineMode : null,
                'deadline_notes' => $hasDeadline ? $deadlineNotes : null,
            ]);

            // Destino pendente não é responsável atual: enquanto o recebimento
            // não for confirmado, a responsabilidade não é atribuída ao destino.
            $protocol->update([
                'status' => ProtocolStatus::ENCAMINHADO,
                'current_sector_id' => null,
                'current_commission_id' => null,
                'destination_sector_id' => $isSector ? $destinationId : $protocol->destination_sector_id,
            ]);

            AuditLogger::log('protocol.forwarded', $protocol, [], [
                'destination_type' => $destinationType->value,
                'to_sector_id' => $isSector ? $destinationId : null,
                'to_commission_id' => $isSector ? null : $destinationId,
                'movement_id' => $movement->id,
                'deadline' => $hasDeadline ? $deadlineDays : null,
                'due_at' => $dueAt?->toDateTimeString(),
            ], 'Protocolo encaminhado para '.$destinationType->label().'.');

            return $movement;
        });
    }

    /**
     * Confirma o recebimento do protocolo pelo destino (setor ou comissão).
     *
     * A atualização da responsabilidade atual e a confirmação do recebimento
     * ocorrem na MESMA transação, evitando que o protocolo apareça como
     * responsabilidade de um destino antes de ele confirmar o recebimento.
     */
    public function confirmReceipt(ProtocolMovement $movement, User $user, ?string $description = null): void
    {
        DB::transaction(function () use ($movement, $user, $description) {
            $this->applyReceipt($movement, $user, $description);
        });
    }

    /**
     * Confirma o recebimento COM o registro de uma divergência na MESMA transação.
     *
     * Uma divergência não impede o recebimento: a ocorrência é criada junto com
     * a confirmação, em uma única transação atômica, para que o documento nunca
     * fique com estado parcialmente atualizado.
     */
    public function confirmReceiptWithDivergence(
        ProtocolMovement $movement,
        User $user,
        DivergenceType|string $type,
        string $description,
        ?string $receiptDescription = null,
    ): ProtocolDivergence {
        return DB::transaction(function () use ($movement, $user, $type, $description, $receiptDescription) {
            $this->applyReceipt($movement, $user, $receiptDescription);

            return $this->persistDivergence($movement->protocol, $user, [
                'type' => $type,
                'description' => $description,
                'protocol_movement_id' => $movement->id,
                'sector_id' => $movement->to_sector_id,
                'commission_id' => $movement->to_commission_id,
            ]);
        });
    }

    /**
     * Aplica a confirmação de recebimento (movimentação + responsabilidade + auditoria).
     * Deve ser executado dentro de uma transação.
     */
    private function applyReceipt(ProtocolMovement $movement, User $user, ?string $description): void
    {
        $movement->update([
            'status' => MovementStatus::RECEBIDO,
            'received_at' => now(),
            'received_by' => $user->id,
            'description' => $description ?: $movement->description,
        ]);

        $protocol = $movement->protocol;
        $protocol->update([
            'status' => ProtocolStatus::RECEBIDO,
            'current_sector_id' => $movement->to_sector_id,
            'current_commission_id' => $movement->to_commission_id,
        ]);

        AuditLogger::log('protocol.received', $protocol, [], [
            'movement_id' => $movement->id,
            'sector_id' => $movement->to_sector_id,
            'commission_id' => $movement->to_commission_id,
        ], 'Recebimento confirmado.');
    }

    /**
     * Registra a recusa formal do recebimento pelo destinatário (setor ou comissão).
     *
     * RECUSA NÃO É RECEBIMENTO: a movimentação original é preservada, o documento
     * NÃO é marcado como recebido e a responsabilidade atual permanece com o
     * remetente até que exista um recebimento válido.
     */
    public function refuseReceipt(
        ProtocolMovement $movement,
        User $user,
        RefusalReason|string $reason,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($movement, $user, $reason, $notes) {
            $reason = $reason instanceof RefusalReason ? $reason : RefusalReason::from($reason);

            $movement->update([
                'status' => MovementStatus::RECUSADO,
                'refusal_reason' => $reason,
                'refusal_notes' => $notes,
                'refused_at' => now(),
                'refused_by' => $user->id,
            ]);

            // A responsabilidade atual NÃO é transferida para o destino recusado.
            AuditLogger::log('protocol.refused', $movement->protocol, [
                'status' => MovementStatus::AGUARDANDO_RECEBIMENTO->value,
            ], [
                'movement_id' => $movement->id,
                'status' => MovementStatus::RECUSADO->value,
                'refusal_reason' => $reason->value,
                'sector_id' => $movement->to_sector_id,
                'commission_id' => $movement->to_commission_id,
            ], 'Recebimento recusado: '.$reason->label().'.');
        });
    }

    /**
     * Registra uma DIVERGÊNCIA sobre o protocolo (durante a permanência no setor,
     * no recebimento ou após o recebimento).
     *
     * DIVERGÊNCIA NÃO É MOVIMENTAÇÃO: não cria movimentação, não marca
     * recebimento e não altera a responsabilidade atual do protocolo.
     *
     * @param array{protocol_movement_id?:int|null,sector_id?:int|null,commission_id?:int|null} $options
     */
    public function recordDivergence(
        Protocol $protocol,
        User $user,
        DivergenceType|string $type,
        string $description,
        array $options = [],
    ): ProtocolDivergence {
        return DB::transaction(function () use ($protocol, $user, $type, $description, $options) {
            return $this->persistDivergence($protocol, $user, [
                'type' => $type,
                'description' => $description,
                'protocol_movement_id' => $options['protocol_movement_id'] ?? null,
                // Quando não informado, a ocorrência fica vinculada à responsabilidade atual.
                'sector_id' => $options['sector_id'] ?? $protocol->current_sector_id,
                'commission_id' => $options['commission_id'] ?? $protocol->current_commission_id,
            ]);
        });
    }

    /**
     * Cria a divergência e registra a auditoria. Deve ser executado dentro de
     * uma transação.
     *
     * @param array{type:DivergenceType|string,description:string,protocol_movement_id?:int|null,sector_id?:int|null,commission_id?:int|null} $data
     */
    private function persistDivergence(Protocol $protocol, User $user, array $data): ProtocolDivergence
    {
        $type = $data['type'] instanceof DivergenceType ? $data['type'] : DivergenceType::from($data['type']);

        $divergence = ProtocolDivergence::create([
            'institution_id' => $protocol->institution_id,
            'protocol_id' => $protocol->id,
            'protocol_movement_id' => $data['protocol_movement_id'] ?? null,
            'sector_id' => $data['sector_id'] ?? null,
            'commission_id' => $data['commission_id'] ?? null,
            'reported_by' => $user->id,
            'type' => $type,
            'description' => $data['description'],
            'status' => DivergenceStatus::ABERTA,
        ]);

        AuditLogger::log('protocol.divergence_created', $protocol, [], [
            'divergence_id' => $divergence->id,
            'type' => $type->value,
            'movement_id' => $divergence->protocol_movement_id,
            'sector_id' => $divergence->sector_id,
            'commission_id' => $divergence->commission_id,
        ], 'Divergência registrada: '.$type->label().'.');

        return $divergence;
    }

    /**
     * Atualiza a situação de uma divergência.
     *
     * O encerramento sem resolução EXIGE justificativa (observação), garantida
     * inclusive no serviço, e não apenas na interface.
     */
    public function updateDivergenceStatus(
        ProtocolDivergence $divergence,
        DivergenceStatus|string $status,
        ?string $notes = null,
    ): void {
        $status = $status instanceof DivergenceStatus ? $status : DivergenceStatus::from($status);

        if ($status === DivergenceStatus::ENCERRADA_SEM_RESOLUCAO && blank($notes)) {
            throw ValidationException::withMessages([
                'resolution_notes' => 'Informe a justificativa para encerrar a divergência sem resolução.',
            ]);
        }

        DB::transaction(function () use ($divergence, $status, $notes) {
            $previous = $divergence->status;

            $divergence->update([
                'status' => $status,
                'resolution_notes' => $notes ?: $divergence->resolution_notes,
                'resolved_at' => $status->isFinal() ? ($divergence->resolved_at ?? now()) : null,
            ]);

            AuditLogger::log('protocol.divergence_updated', $divergence->protocol, [
                'status' => $previous->value,
            ], [
                'divergence_id' => $divergence->id,
                'status' => $status->value,
            ], 'Divergência atualizada para '.$status->label().'.');
        });
    }

    /**
     * Retifica uma movimentação histórica (FASE 2).
     *
     * RETIFICAÇÃO NÃO É EXCLUSÃO: os dados anteriores são preservados em
     * `protocol_movement_corrections` e a movimentação original nunca é apagada.
     * Apenas campos revisáveis (descrição, tipo, prazo, destino) podem ser
     * alterados. Usuário original, datas originais, recusas e divergências
     * permanecem intocados. Alterar o destino exige autorização elevada e
     * registra explicitamente a consequência sobre a responsabilidade atual.
     *
     * @param array<string,mixed> $changes
     */
    public function correctMovement(
        ProtocolMovement $movement,
        User $user,
        string $reason,
        array $changes,
        bool $authorizedDestinationChange = false,
    ): ProtocolMovementCorrection {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Informe o motivo da retificação.',
            ]);
        }

        // Whitelist: somente campos revisáveis. Nunca usuário/datas originais,
        // recusas, divergências ou a possibilidade de apagar a movimentação.
        $filtered = [];

        foreach (array_intersect_key($changes, array_flip(self::CORRECTABLE_FIELDS)) as $field => $value) {
            // Campos de destino podem ser limpos (null); os demais precisam de valor.
            if ($value === null && ! in_array($field, self::DESTINATION_FIELDS, true)) {
                continue;
            }

            $filtered[$field] = $value;
        }

        $changes = $filtered;

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' => 'Informe ao menos um campo a retificar.',
            ]);
        }

        $touchesDestination = array_intersect(array_keys($changes), self::DESTINATION_FIELDS) !== [];

        if ($touchesDestination && ! $authorizedDestinationChange) {
            throw ValidationException::withMessages([
                'destination_id' => 'Alterar o destino exige autorização elevada.',
            ]);
        }

        return DB::transaction(function () use ($movement, $user, $reason, $changes, $touchesDestination) {
            $original = [];

            foreach (array_keys($changes) as $field) {
                $original[$field] = $movement->getRawOriginal($field);
            }

            $previousDestination = [
                'to_sector_id' => $movement->to_sector_id,
                'to_commission_id' => $movement->to_commission_id,
            ];

            $movement->update($changes);

            $correction = ProtocolMovementCorrection::create([
                'institution_id' => $movement->institution_id,
                'protocol_id' => $movement->protocol_id,
                'protocol_movement_id' => $movement->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'original_data' => $original,
                'corrected_data' => $this->storableChanges($changes),
            ]);

            // Mudança de destino exige registro explícito da consequência sobre
            // a responsabilidade atual quando a movimentação é a responsável vigente.
            $consequence = $touchesDestination
                ? $this->applyDestinationConsequence($movement, $previousDestination)
                : null;

            AuditLogger::log('protocol.movement_corrected', $movement->protocol, $original, [
                'movement_id' => $movement->id,
                'correction_id' => $correction->id,
                'reason' => $reason,
                'changed_fields' => array_keys($changes),
                'consequence' => $consequence,
            ], 'Movimentação retificada.');

            return $correction;
        });
    }

    /**
     * Normaliza valores para armazenamento JSON (enum → valor, data → string).
     *
     * @param array<string,mixed> $changes
     * @return array<string,mixed>
     */
    private function storableChanges(array $changes): array
    {
        $out = [];

        foreach ($changes as $field => $value) {
            $out[$field] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            };
        }

        return $out;
    }

    /**
     * Ajusta a responsabilidade atual do protocolo quando a retificação altera
     * o destino da movimentação responsável vigente. Deve rodar em transação.
     *
     * @param array{to_sector_id:int|null,to_commission_id:int|null} $previous
     */
    private function applyDestinationConsequence(ProtocolMovement $movement, array $previous): ?string
    {
        $protocol = $movement->protocol;

        $wasResponsible =
            ($previous['to_sector_id'] !== null && $protocol->current_sector_id === $previous['to_sector_id'])
            || ($previous['to_commission_id'] !== null && $protocol->current_commission_id === $previous['to_commission_id']);

        if (! $wasResponsible) {
            return null;
        }

        $protocol->update([
            'current_sector_id' => $movement->to_sector_id,
            'current_commission_id' => $movement->to_commission_id,
        ]);

        return 'Responsabilidade atual transferida para '.$movement->destinationLabel().'.';
    }

    /**
     * Registra um andamento (despacho, parecer, conclusão, arquivamento, devolução...).
     */
    public function register(Protocol $protocol, User $user, MovementType $type, ?string $description, array $extra = []): ProtocolMovement
    {
        return DB::transaction(function () use ($protocol, $user, $type, $description, $extra) {
            $status = match ($type) {
                MovementType::CONCLUSAO => ProtocolStatus::CONCLUIDO,
                MovementType::ARQUIVAMENTO => ProtocolStatus::ARQUIVADO,
                MovementType::DEVOLUCAO => ProtocolStatus::ENCAMINHADO,
                MovementType::SOLICITACAO_COMPLEMENTACAO => ProtocolStatus::AGUARDANDO_PROVIDENCIAS,
                MovementType::PARECER, MovementType::DESPACHO => ProtocolStatus::EM_ANALISE,
                default => $protocol->status,
            };

            $redirectToSector = ! empty($extra['to_sector_id']);
            $targetSectorId = $redirectToSector ? (int) $extra['to_sector_id'] : $protocol->current_sector_id;
            $targetCommissionId = $redirectToSector ? null : $protocol->current_commission_id;

            $movement = ProtocolMovement::create([
                'institution_id' => $protocol->institution_id,
                'protocol_id' => $protocol->id,
                'from_sector_id' => $protocol->current_sector_id,
                'from_commission_id' => $protocol->current_commission_id,
                'to_sector_id' => $targetSectorId,
                'to_commission_id' => $targetCommissionId,
                'destination_type' => $targetCommissionId
                    ? DestinationType::COMMISSION
                    : ($targetSectorId ? DestinationType::SECTOR : null),
                'from_user_id' => $user->id,
                'type' => $type,
                'status' => MovementStatus::RECEBIDO,
                'received_at' => now(),
                'received_by' => $user->id,
                'description' => $description,
            ]);

            $protocol->update([
                'status' => $status,
                'current_sector_id' => $targetSectorId,
                'current_commission_id' => $targetCommissionId,
            ]);

            AuditLogger::log('protocol.movement', $protocol, [], [
                'type' => $type->value,
                'movement_id' => $movement->id,
            ], $type->label().' registrado.');

            return $movement;
        });
    }
}
