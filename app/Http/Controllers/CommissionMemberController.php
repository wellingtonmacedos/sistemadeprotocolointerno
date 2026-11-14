<?php

namespace App\Http\Controllers;

use App\Enums\CommissionMemberRole;
use App\Models\Commission;
use App\Models\CommissionMember;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Composição histórica das comissões.
 * Nunca apaga mandatos anteriores: encerra o mandato vigente (end_date)
 * e registra um novo, preservando o histórico.
 */
class CommissionMemberController extends Controller
{
    public function store(Request $request, Commission $commission)
    {
        $data = $request->validate([
            'councilor_id' => ['required', Tenant::scopedExists('councilors')],
            'role' => ['required', Rule::in(array_keys(CommissionMemberRole::options()))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Presidente e vice-presidente são únicos na composição vigente:
        // ao registrar um novo titular, encerra-se o mandato anterior.
        if (in_array($data['role'], ['presidente', 'vice_presidente'], true) && empty($data['end_date'])) {
            $commission->currentMembers()
                ->where('role', $data['role'])
                ->get()
                ->each(function (CommissionMember $current) use ($data) {
                    $current->update(['end_date' => $data['start_date']]);
                    AuditLogger::log(
                        'commission.member.ended',
                        $current,
                        [],
                        ['end_date' => $data['start_date']],
                        'Mandato encerrado automaticamente (novo titular): '.$current->role
                    );
                });
        }

        $data['institution_id'] = $commission->institution_id;

        $member = $commission->members()->create($data);

        AuditLogger::log(
            'commission.member.added',
            $commission,
            [],
            $data,
            'Membro adicionado à comissão: '.$commission->name
        );

        return back()->with('success', 'Membro adicionado à composição da comissão.');
    }

    /** Encerra o mandato vigente (mantém o histórico). */
    public function end(Request $request, CommissionMember $member)
    {
        $data = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:'.$member->start_date->toDateString()],
        ]);

        $before = ['end_date' => $member->end_date?->toDateString()];
        $member->update(['end_date' => $data['end_date']]);

        AuditLogger::log(
            'commission.member.ended',
            $member,
            $before,
            ['end_date' => $data['end_date']],
            'Mandato encerrado na comissão #'.$member->commission_id
        );

        return back()->with('success', 'Mandato encerrado. O histórico foi preservado.');
    }

    /** Remove um lançamento incorreto (soft delete; mantém rastreabilidade). */
    public function destroy(Request $request, CommissionMember $member)
    {
        $request->validate([
            'justification' => ['required', 'string', 'max:500'],
        ]);

        $old = $member->getAttributes();
        $member->delete();

        AuditLogger::log(
            'commission.member.removed',
            $member,
            $old,
            ['justification' => $request->input('justification')],
            'Lançamento de composição removido: '.$request->input('justification')
        );

        return back()->with('success', 'Lançamento removido com justificativa registrada em auditoria.');
    }
}
