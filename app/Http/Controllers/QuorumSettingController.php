<?php

namespace App\Http\Controllers;

use App\Enums\PropositionModality;
use App\Enums\QuorumRule;
use App\Models\QuorumSetting;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * FASE 4 — Configuração de quórum por instituição e modalidade.
 */
class QuorumSettingController extends Controller
{
    public function index()
    {
        return view('quorum.index', [
            'settings' => QuorumSetting::query()->orderBy('modality')->get(),
            'rules' => QuorumRule::options(),
            'modalities' => PropositionModality::options(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'modality' => ['nullable', 'in:'.implode(',', array_keys(PropositionModality::options()))],
            'rule' => ['required', 'in:'.implode(',', array_keys(QuorumRule::options()))],
            'min_presence' => ['nullable', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['modality' => 'modalidade', 'rule' => 'regra de quórum', 'min_presence' => 'presença mínima']);

        $institutionId = Tenant::id() ?? $request->user()->institution_id;
        $modality = $data['modality'] ?? null;

        $setting = QuorumSetting::persist($institutionId, $modality, [
            'rule' => $data['rule'],
            'min_presence' => $data['min_presence'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLogger::log('quorum.saved', null, [], [
            'modality' => $modality,
            'rule' => $setting->rule->value,
        ], 'Regra de quórum configurada para '.($modality ? PropositionModality::from($modality)->label() : 'todas as modalidades').'.');

        return back()->with('success', 'Regra de quórum salva.');
    }

    public function destroy(Request $request, QuorumSetting $quorum)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('quorum.deleted', null, [
            'scope_key' => $quorum->scope_key,
        ], [], 'Regra de quórum removida. Justificativa: '.$data['justification']);

        $quorum->delete();

        return back()->with('success', 'Regra de quórum removida.');
    }
}
