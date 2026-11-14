<?php

namespace App\Services;

use App\Enums\PropositionStatus;
use App\Models\NumberingSequence;
use App\Models\Proposition;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Fluxo das proposições legislativas: rascunho -> envio -> análise -> aprovação -> registro oficial.
 */
class PropositionService
{
    public function __construct(private NumberingService $numbering) {}

    public function create(array $data, User $user): Proposition
    {
        return DB::transaction(function () use ($data, $user) {
            $year = (int) ($data['year'] ?? now()->year);

            $proposition = Proposition::create([
                ...collect($data)->except('coauthor_ids')->all(),
                'institution_id' => $data['institution_id'] ?? Tenant::id(),
                'year' => $year,
                'status' => PropositionStatus::RASCUNHO,
                'created_by' => $user->id,
            ]);

            $this->syncAuthors($proposition, $data['coauthor_ids'] ?? []);

            AuditLogger::log('proposition.created', $proposition, [], [
                'modality' => $proposition->modality->value,
            ], 'Proposição criada como rascunho.');

            return $proposition;
        });
    }

    public function update(Proposition $proposition, array $data): Proposition
    {
        $old = $proposition->only(['title', 'ementa', 'modality', 'status']);

        DB::transaction(function () use ($proposition, $data, $old) {
            $proposition->update(collect($data)->except('coauthor_ids')->all());
            $this->syncAuthors($proposition, $data['coauthor_ids'] ?? []);

            AuditLogger::log('proposition.updated', $proposition, $old, $proposition->only([
                'title', 'ementa', 'modality', 'status',
            ]), 'Proposição atualizada.');
        });

        return $proposition->refresh();
    }

    public function submit(Proposition $proposition, User $user): void
    {
        $proposition->update([
            'status' => PropositionStatus::ENVIADA,
            'submitted_at' => now(),
        ]);

        AuditLogger::log('proposition.submitted', $proposition, [], [], 'Enviada à Secretaria Legislativa.');
    }

    public function review(Proposition $proposition, User $user, PropositionStatus $decision, ?string $notes = null): void
    {
        $status = match ($decision) {
            PropositionStatus::APROVADA => PropositionStatus::APROVADA,
            PropositionStatus::REJEITADA => PropositionStatus::REJEITADA,
            PropositionStatus::DEVOLVIDA => PropositionStatus::DEVOLVIDA,
            default => PropositionStatus::EM_ANALISE,
        };

        $proposition->update([
            'status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        AuditLogger::log('proposition.reviewed', $proposition, [], [
            'status' => $status->value,
        ], 'Análise registrada: '.$status->label());
    }

    /**
     * Efetiva o registro oficial, gerando a numeração sequencial definitiva.
     */
    public function registerOfficial(Proposition $proposition, User $user): Proposition
    {
        return DB::transaction(function () use ($proposition, $user) {
            if ($proposition->official_number) {
                return $proposition;
            }

            $number = $this->numbering->next(
                $proposition->institution_id,
                NumberingSequence::SCOPE_PROPOSITION,
                $proposition->modality->value,
                (int) $proposition->year
            );

            $official = $proposition->modality->abbreviation().' nº '
                .$this->numbering->format($number, (int) $proposition->year, 3);

            $proposition->update([
                'number' => $number,
                'official_number' => $official,
                'status' => PropositionStatus::REGISTRADA,
                'registered_by' => $user->id,
                'registered_at' => now(),
            ]);

            AuditLogger::log('proposition.registered', $proposition, [], [
                'official_number' => $official,
            ], 'Registro oficial efetivado: '.$official);

            return $proposition->refresh();
        });
    }

    protected function syncAuthors(Proposition $proposition, array $coauthorIds): void
    {
        $ids = collect($coauthorIds)->filter()->unique();

        if ($proposition->author_councilor_id) {
            $ids->push($proposition->author_councilor_id);
        }

        $sync = $ids->unique()->mapWithKeys(fn ($id) => [
            $id => ['is_primary' => (int) $id === (int) $proposition->author_councilor_id],
        ])->all();

        $proposition->authors()->sync($sync);
    }
}
