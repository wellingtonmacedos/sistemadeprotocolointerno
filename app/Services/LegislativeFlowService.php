<?php

namespace App\Services;

use App\Models\LegislativeFlow;
use App\Models\LegislativeFlowVersion;
use Illuminate\Support\Facades\DB;

/**
 * Regras de configuração e versionamento dos fluxos legislativos.
 * Versões publicadas são imutáveis: alterações geram nova versão.
 */
class LegislativeFlowService
{
    /**
     * Cria uma nova versão para o fluxo. Se $copyFrom for informado, as etapas
     * são copiadas; caso contrário, aplica a configuração inicial sugerida.
     */
    public function createVersion(
        LegislativeFlow $flow,
        string $status = 'rascunho',
        ?LegislativeFlowVersion $copyFrom = null
    ): LegislativeFlowVersion {
        return DB::transaction(function () use ($flow, $status, $copyFrom) {
            $next = (int) $flow->versions()->max('version') + 1;

            $version = LegislativeFlowVersion::create([
                'institution_id' => $flow->institution_id,
                'legislative_flow_id' => $flow->id,
                'version' => $next,
                'status' => $status,
                'is_current' => false,
                'created_by' => auth()->id(),
            ]);

            $steps = $copyFrom
                ? $copyFrom->steps->map(fn ($s) => $s->only($this->stepFields()))->all()
                : $this->defaultStepsFor($flow->modality);

            foreach (array_values($steps) as $i => $step) {
                $version->steps()->create(array_merge($step, [
                    'institution_id' => $flow->institution_id,
                    'position' => $i + 1,
                ]));
            }

            return $version;
        });
    }

    /** Publica a versão e a torna a vigente do fluxo. */
    public function publish(LegislativeFlowVersion $version): void
    {
        DB::transaction(function () use ($version) {
            LegislativeFlowVersion::where('legislative_flow_id', $version->legislative_flow_id)
                ->update(['is_current' => false]);

            $version->update([
                'status' => 'publicado',
                'is_current' => true,
                'published_at' => now(),
            ]);
        });
    }

    /** @return array<int,string> */
    public function stepFields(): array
    {
        return [
            'name', 'description', 'step_type', 'sector_id', 'commission_id',
            'responsible_user_id', 'deadline_days', 'is_required', 'can_skip',
            'requires_document', 'advance_condition', 'allows_return',
            'is_conclusive', 'conclusion_status',
        ];
    }

    /**
     * Configuração inicial sugerida por modalidade (não obrigatória).
     *
     * @return array<int,array<string,mixed>>
     */
    public function defaultStepsFor(string $modality): array
    {
        $full = [
            ['name' => 'Protocolo / Recebimento', 'step_type' => 'recebimento', 'is_required' => true],
            ['name' => 'Análise da Secretaria Legislativa', 'step_type' => 'analise', 'is_required' => true],
            ['name' => 'Despacho da Presidência', 'step_type' => 'despacho', 'is_required' => true],
            ['name' => 'Análise Jurídica', 'step_type' => 'juridico', 'is_required' => false, 'can_skip' => true],
            ['name' => 'Análise pelas Comissões competentes', 'step_type' => 'comissao', 'is_required' => true],
            ['name' => 'Consolidação dos pareceres', 'step_type' => 'consolidacao', 'is_required' => true],
            ['name' => 'Aguardando inclusão na pauta', 'step_type' => 'pauta', 'is_required' => true],
            ['name' => 'Inclusão na ordem do dia', 'step_type' => 'pauta', 'is_required' => true],
            ['name' => 'Discussão em Plenário', 'step_type' => 'plenario', 'is_required' => true],
            ['name' => 'Votação', 'step_type' => 'votacao', 'is_required' => true],
            ['name' => 'Registro do resultado', 'step_type' => 'resultado', 'is_required' => true],
            ['name' => 'Redação final', 'step_type' => 'redacao_final', 'is_required' => false, 'can_skip' => true],
            ['name' => 'Encaminhamento para sanção / promulgação', 'step_type' => 'sancao', 'is_required' => false, 'can_skip' => true],
            ['name' => 'Conclusão / Arquivamento', 'step_type' => 'conclusao', 'is_required' => true, 'is_conclusive' => true, 'conclusion_status' => 'concluida'],
        ];

        $simple = [
            ['name' => 'Protocolo / Recebimento', 'step_type' => 'recebimento', 'is_required' => true],
            ['name' => 'Análise da Secretaria Legislativa', 'step_type' => 'analise', 'is_required' => true],
            ['name' => 'Despacho da Presidência', 'step_type' => 'despacho', 'is_required' => false, 'can_skip' => true],
            ['name' => 'Votação', 'step_type' => 'votacao', 'is_required' => true],
            ['name' => 'Registro do resultado', 'step_type' => 'resultado', 'is_required' => true],
            ['name' => 'Conclusão / Arquivamento', 'step_type' => 'conclusao', 'is_required' => true, 'is_conclusive' => true, 'conclusion_status' => 'concluida'],
        ];

        $base = in_array($modality, ['projeto_lei', 'projeto_resolucao', 'projeto_decreto_legislativo'], true)
            ? $full
            : $simple;

        return array_map(fn ($step) => array_merge([
            'description' => null,
            'sector_id' => null,
            'commission_id' => null,
            'responsible_user_id' => null,
            'deadline_days' => null,
            'is_required' => true,
            'can_skip' => false,
            'requires_document' => false,
            'advance_condition' => null,
            'allows_return' => false,
            'is_conclusive' => false,
            'conclusion_status' => null,
        ], $step), $base);
    }
}
