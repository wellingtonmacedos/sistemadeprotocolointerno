<?php

namespace App\Services;

use App\Enums\QuorumRule;
use App\Enums\VotingResult;
use App\Models\Voting;
use Illuminate\Validation\ValidationException;

/**
 * FASE 4 — Apuração e validação de votação conforme o quórum configurado.
 */
class VotingService
{
    /**
     * Valida o resultado declarado contra a regra de quórum da instituição.
     *
     * Votações simbólicas não exigem voto individual e, portanto, não sofrem
     * checagem de quórum — apenas registro do resultado consolidado.
     */
    public function validateResult(Voting $voting, VotingResult $result): void
    {
        if (! $result->requiresQuorumCheck() || ! $voting->type->requiresIndividualVotes()) {
            return;
        }

        $rule = $voting->quorum_rule ?? QuorumRule::MAIORIA_SIMPLES;
        $present = $voting->present_count;

        if ($voting->quorum_min_presence && $present < $voting->quorum_min_presence) {
            throw ValidationException::withMessages([
                'result' => "Quórum mínimo não atingido: são exigidos {$voting->quorum_min_presence} presentes e compareceram {$present}.",
            ]);
        }

        $approved = $rule->approves($voting->counts(), $present);

        $incompatible = ($result->isApproval() && ! $approved)
            || ($result === VotingResult::REJEITADA && $approved);

        if ($incompatible) {
            throw ValidationException::withMessages([
                'result' => "O resultado informado é incompatível com a regra de quórum configurada ({$rule->label()}).",
            ]);
        }
    }
}
