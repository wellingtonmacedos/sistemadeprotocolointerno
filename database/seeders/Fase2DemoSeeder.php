<?php

namespace Database\Seeders;

use App\Enums\AgendaItemStatus;
use App\Enums\AppreciationType;
use App\Enums\DeadlineMode;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\DivergenceStatus;
use App\Enums\DivergenceType;
use App\Enums\DocumentType;
use App\Enums\ForwardingStatus;
use App\Enums\MovementType;
use App\Enums\OpinionResult;
use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolActionType;
use App\Enums\ProtocolPriority;
use App\Enums\PropositionModality;
use App\Enums\PropositionStatus;
use App\Enums\QuorumRule;
use App\Enums\RefusalReason;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Enums\VoteType;
use App\Enums\VotingResult;
use App\Enums\VotingType;
use App\Models\Commission;
use App\Models\CommissionMember;
use App\Models\CommissionOpinion;
use App\Models\Councilor;
use App\Models\Institution;
use App\Models\LegislativeSession;
use App\Models\Protocol;
use App\Models\ProtocolAction;
use App\Models\Proposition;
use App\Models\PropositionForwarding;
use App\Models\QuorumSetting;
use App\Models\Sector;
use App\Models\SessionAgendaItem;
use App\Models\User;
use App\Models\Voting;
use App\Models\VotingRecord;
use App\Services\ProtocolService;
use App\Services\PropositionService;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Cenário de demonstração "real" para teste visual/homologação.
 *
 * Popula a Câmara Demo com protocolos tramitados (prazos no prazo / próximos /
 * vencidos, recusa, divergência aberta e resolvida, retificação, providências e
 * comissão como destino) e com o fluxo legislativo (comissões, proposições,
 * pareceres, sessão com pauta e votação apurada).
 *
 * Usa os serviços reais (ProtocolService / PropositionService) para que o
 * estado gravado reflita exatamente as regras de negócio da aplicação.
 */
class Fase2DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);

        $institution = Institution::withoutGlobalScopes()
            ->where('slug', 'camara-municipal-demo')->firstOrFail();

        if (Protocol::withoutGlobalScopes()->where('institution_id', $institution->id)->exists()) {
            $this->command?->warn('Cenário de demonstração já existe — nada foi recriado.');

            return;
        }

        Tenant::set($institution);

        $sector = fn (string $name): Sector => Sector::where('slug', Str::slug($name))->firstOrFail();
        $user = fn (string $email): User => User::withoutGlobalScopes()->where('email', $email)->firstOrFail();

        $admin = $user('admin@camarademo.local');
        $secretaria = $user('secretaria@camarademo.local');

        // O administrador passa a responder pelo Protocolo: assim a Caixa de
        // Entrada (que respeita o vínculo de setor) exibe o cenário completo,
        // sem perder nenhuma permissão administrativa.
        $admin->update(['sector_id' => $sector('Protocolo')->id]);

        Auth::login($admin);

        $councilors = Councilor::orderBy('id')->get()->keyBy('name');
        $carlos = $councilors['Carlos Vereador'];
        $ana = $councilors['Ana Souza'];
        $pedro = $councilors['Pedro Lima'];
        $juliana = $councilors['Juliana Alves'];

        $svc = app(ProtocolService::class);

        // ---------------------------------------------------------------- //
        // Comissões e composição
        // ---------------------------------------------------------------- //
        $ccj = $this->commission($institution, 'Comissão de Constituição e Justiça', 'CCJ', 1,
            'Análise de constitucionalidade, legalidade e regimentalidade das matérias.');
        $cfo = $this->commission($institution, 'Comissão de Finanças e Orçamento', 'CFO', 2,
            'Análise orçamentária e financeira das proposições.');

        CommissionMember::create([
            'institution_id' => $institution->id, 'commission_id' => $ccj->id,
            'councilor_id' => $ana->id, 'role' => 'presidente',
            'start_date' => now()->subMonths(3)->toDateString(),
        ]);
        CommissionMember::create([
            'institution_id' => $institution->id, 'commission_id' => $ccj->id,
            'councilor_id' => $pedro->id, 'role' => 'relator',
            'start_date' => now()->subMonths(3)->toDateString(),
        ]);
        CommissionMember::create([
            'institution_id' => $institution->id, 'commission_id' => $cfo->id,
            'councilor_id' => $juliana->id, 'role' => 'presidente',
            'start_date' => now()->subMonths(3)->toDateString(),
        ]);

        $createProtocol = function (array $attrs) use ($svc, $institution, $admin): Protocol {
            return $svc->create(array_merge([
                'institution_id' => $institution->id,
                'document_type' => DocumentType::OFICIO->value,
                'priority' => ProtocolPriority::NORMAL->value,
            ], $attrs), $admin);
        };

        $receiveInitial = function (Protocol $protocol) use ($svc, $admin): void {
            $initial = $protocol->movements()->reorder('id')->first();

            if ($initial && $initial->isPending()) {
                $svc->confirmReceipt($initial, $admin);
            }
        };

        // ---------------------------------------------------------------- //
        // P1 — Recebido com prazo (no prazo)
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(5));
        $p1 = $createProtocol([
            'subject' => 'Solicitação de informações – iluminação pública do Bairro Centro',
            'sender_name' => 'Ministério Público Estadual',
            'destination_sector_id' => $sector('Protocolo')->id,
            'description' => 'Ofício requisitando informações técnicas sobre a rede de iluminação.',
        ]);
        $receiveInitial($p1);
        $this->action($institution, $p1, $p1->movements()->reorder('id')->first(), $sector('Protocolo'),
            ProtocolActionType::CONFERENCIA_DOCUMENTAL, 'Documentação conferida e autuada no sistema.',
            ProtocolActionStatus::CONCLUIDA);
        $m1 = $svc->forward($p1->fresh(), $admin, DestinationType::SECTOR,
            $sector('Assessoria Jurídica')->id, 'Para análise jurídica da requisição.',
            MovementType::ENCAMINHAMENTO, null, 10, DeadlineType::REGIMENTAL, DeadlineMode::CORRIDOS);
        Carbon::setTestNow();
        $svc->confirmReceipt($m1, $admin);

        // ---------------------------------------------------------------- //
        // P2 — Recusa de recebimento + reencaminhamento
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(4));
        $p2 = $createProtocol([
            'subject' => 'Prestação de contas – convênio nº 45/2026',
            'sender_name' => 'Associação Comunitária do Bairro Norte',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p2);
        $mRefused = $svc->forward($p2->fresh(), $admin, DestinationType::SECTOR,
            $sector('Financeiro')->id, 'Encaminhado para conferência contábil.');
        Carbon::setTestNow();
        $svc->refuseReceipt($mRefused, $admin, RefusalReason::DOCUMENTO_INCOMPLETO,
            'Faltam os comprovantes de despesa e o balanço anexo. Devolvido para regularização.');
        $mFixed = $svc->forward($p2->fresh(), $admin, DestinationType::SECTOR,
            $sector('Controle Interno')->id, 'Documentação complementada pelo interessado.');
        $svc->confirmReceipt($mFixed, $admin);

        // ---------------------------------------------------------------- //
        // P3 — Divergência ABERTA (recebimento com ressalva)
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(3));
        $p3 = $createProtocol([
            'subject' => 'Requerimento de cessão de servidor',
            'sender_name' => 'Câmara Municipal de Município Vizinho',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p3);
        $m3 = $svc->forward($p3->fresh(), $admin, DestinationType::SECTOR,
            $sector('Secretaria Legislativa')->id);
        Carbon::setTestNow();
        $svc->confirmReceiptWithDivergence($m3, $admin, DivergenceType::ANEXO_FALTANTE,
            'Recebido sem o anexo III (ficha funcional do servidor).');

        // ---------------------------------------------------------------- //
        // P4 — Divergência RESOLVIDA + providência pendente
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(8));
        $p4 = $createProtocol([
            'subject' => 'Pedido de progressão funcional',
            'sender_name' => 'Sindicato dos Servidores Municipais',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p4);
        $m4 = $svc->forward($p4->fresh(), $admin, DestinationType::SECTOR,
            $sector('Recursos Humanos')->id, 'Para análise do pedido de progressão.');
        Carbon::setTestNow();
        $svc->confirmReceipt($m4, $admin);
        $divergence = $svc->recordDivergence($p4->fresh(), $admin,
            DivergenceType::INFORMACAO_INCONSISTENTE,
            'Nome do servidor diverge entre o ofício de encaminhamento e a ficha cadastral.');
        $svc->updateDivergenceStatus($divergence, DivergenceStatus::RESOLVIDA,
            'Divergência esclarecida por e-mail institucional; cadastro ajustado.');
        $this->action($institution, $p4, $m4, $sector('Recursos Humanos'),
            ProtocolActionType::JUNTADA_DOCUMENTOS, 'Juntada da ficha funcional retificada.',
            ProtocolActionStatus::EM_ANDAMENTO, true);

        // ---------------------------------------------------------------- //
        // P5 — Prazo VENCIDO (ainda aguardando recebimento)
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(12));
        $p5 = $createProtocol([
            'subject' => 'Indicação para melhorias na sinalização viária',
            'sender_name' => 'Conselho Comunitário de Segurança',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p5);
        $svc->forward($p5->fresh(), $admin, DestinationType::SECTOR,
            $sector('Presidência')->id, 'Para despacho e designação de relator.',
            MovementType::ENCAMINHAMENTO, null, 3, DeadlineType::DESPACHO, DeadlineMode::CORRIDOS);
        Carbon::setTestNow();

        // ---------------------------------------------------------------- //
        // P6 — Prazo PRÓXIMO DO VENCIMENTO
        // ---------------------------------------------------------------- //
        $p6 = $createProtocol([
            'subject' => 'Solicitação de cópia de lei municipal',
            'sender_name' => 'Escritório de Advocacia Silva & Associados',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p6);
        $svc->forward($p6->fresh(), $admin, DestinationType::SECTOR,
            $sector('Arquivo')->id, 'Encaminhado para levantamento documental.',
            MovementType::ENCAMINHAMENTO, null, 2, DeadlineType::ADMINISTRATIVO, DeadlineMode::CORRIDOS);

        // ---------------------------------------------------------------- //
        // P7 — Retificação de movimentação (append-only)
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(6));
        $p7 = $createProtocol([
            'subject' => 'Termo de cooperação técnica – universidade',
            'sender_name' => 'Universidade Federal do Ceará',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p7);
        $m7 = $svc->forward($p7->fresh(), $admin, DestinationType::SECTOR,
            $sector('Financeiro')->id, 'Encaminhamento inicial para análise.',
            MovementType::ENCAMINHAMENTO, null, 5, DeadlineType::ADMINISTRATIVO, DeadlineMode::CORRIDOS);
        Carbon::setTestNow();
        $svc->confirmReceipt($m7, $admin);
        $svc->correctMovement($m7->fresh(), $admin,
            'Prazo concedido incorretamente: o regimental são 8 dias úteis.',
            [
                'description' => 'Encaminhamento para análise financeira (prazo retificado).',
                'deadline' => 8,
                'deadline_type' => DeadlineType::REGIMENTAL->value,
                'deadline_mode' => DeadlineMode::UTEIS->value,
                'due_at' => DeadlineMode::UTEIS->addTo($m7->created_at, 8),
            ]);

        // ---------------------------------------------------------------- //
        // P8 — Protocolo CONCLUÍDO
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(15));
        $p8 = $createProtocol([
            'subject' => 'Reclamação sobre coleta de resíduos',
            'sender_name' => 'Morador do Bairro Sul',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p8);
        $m8 = $svc->forward($p8->fresh(), $admin, DestinationType::SECTOR,
            $sector('Secretaria Legislativa')->id);
        Carbon::setTestNow(now()->subDays(13));
        $svc->confirmReceipt($m8, $admin);
        Carbon::setTestNow(now()->subDays(10));
        $svc->register($p8->fresh(), $admin, MovementType::CONCLUSAO,
            'Demanda atendida e resposta encaminhada ao interessado.');
        Carbon::setTestNow();

        // ---------------------------------------------------------------- //
        // P9 — Protocolo sob responsabilidade de COMISSÃO
        // ---------------------------------------------------------------- //
        Carbon::setTestNow(now()->subDays(2));
        $p9 = $createProtocol([
            'subject' => 'Consulta sobre constitucionalidade de projeto de lei',
            'sender_name' => 'Gabinete da Vereadora Ana Souza',
            'destination_sector_id' => $sector('Protocolo')->id,
        ]);
        $receiveInitial($p9);
        $m9 = $svc->forward($p9->fresh(), $admin, DestinationType::SECTOR,
            $sector('Secretaria Legislativa')->id);
        Carbon::setTestNow(now()->subDays(1));
        $svc->confirmReceipt($m9, $admin);
        $mComissao = $svc->forward($p9->fresh(), $admin, DestinationType::COMMISSION, $ccj->id,
            'Para parecer da Comissão de Constituição e Justiça.');
        Carbon::setTestNow();
        $svc->confirmReceipt($mComissao, $admin);

        // ---------------------------------------------------------------- //
        // Fluxo legislativo — proposições, pareceres, sessão, votação
        // ---------------------------------------------------------------- //
        $this->legislativeScenario($institution, $admin, $secretaria, $ccj, $cfo,
            $carlos, $ana, $pedro, $juliana);

        Carbon::setTestNow();
        Auth::logout();
        Tenant::set(null);

        $this->command?->info('Cenário de demonstração (FASE 2 + legislativo) criado com sucesso.');
        $this->command?->line('Login admin: admin@camarademo.local / Senha@12345');
        $this->command?->line('Login protocolo: protocolo@camarademo.local / Senha@12345');
    }

    private function commission(Institution $institution, string $name, string $acronym, int $order, string $competences): Commission
    {
        return Commission::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'acronym' => $acronym,
            'type' => 'permanente',
            'is_active' => true,
            'display_order' => $order,
            'competences' => $competences,
            'allowed_modalities' => array_keys(PropositionModality::options()),
        ]);
    }

    private function action(
        Institution $institution,
        Protocol $protocol,
        ?object $movement,
        Sector $sector,
        ProtocolActionType $type,
        string $description,
        ProtocolActionStatus $status,
        bool $followUp = false,
    ): void {
        ProtocolAction::create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'protocol_movement_id' => $movement?->id,
            'sector_id' => $sector->id,
            'user_id' => Auth::id(),
            'action_type' => $type,
            'description' => $description,
            'status' => $status,
            'occurred_at' => now(),
            'requires_follow_up' => $followUp,
        ]);
    }

    private function legislativeScenario(
        Institution $institution,
        User $admin,
        User $secretaria,
        Commission $ccj,
        Commission $cfo,
        Councilor $carlos,
        Councilor $ana,
        Councilor $pedro,
        Councilor $juliana,
    ): void {
        $svc = app(PropositionService::class);
        $year = (int) now()->year;

        $make = function (PropositionModality $modality, string $title, string $ementa, Councilor $author, array $coauthors = []) use ($svc, $institution, $admin, $year): Proposition {
            $proposition = $svc->create([
                'institution_id' => $institution->id,
                'modality' => $modality->value,
                'year' => $year,
                'title' => $title,
                'ementa' => $ementa,
                'text' => 'Art. 1º Esta proposição disciplina a matéria indicada na ementa.',
                'justification' => 'A medida atende ao interesse público e à boa técnica legislativa.',
                'author_councilor_id' => $author->id,
            ], $admin);

            $sync = collect($coauthors)->push($author)->unique('id')->mapWithKeys(fn (Councilor $c) => [
                $c->id => ['is_primary' => $c->id === $author->id],
            ])->all();

            $proposition->authors()->sync($sync);

            return $proposition;
        };

        // --- Prop 1: encaminhada à CCJ, com parecer favorável --------------- //
        $prop1 = $make(PropositionModality::PROJETO_LEI,
            'Dispõe sobre o programa municipal de capacitação digital',
            'Institui o programa de capacitação digital para servidores públicos municipais.',
            $carlos, [$ana]);
        $svc->submit($prop1, $admin);
        $svc->review($prop1, $secretaria, PropositionStatus::EM_ANALISE,
            'Encaminhada para análise das comissões competentes.');

        $forwarding1 = PropositionForwarding::create([
            'institution_id' => $institution->id,
            'proposition_id' => $prop1->id,
            'commission_id' => $ccj->id,
            'status' => ForwardingStatus::EM_ANALISE,
            'deadline' => now()->addDays(20)->toDateString(),
            'notes' => 'Encaminhada para parecer sobre constitucionalidade.',
            'forwarded_by' => $secretaria->id,
            'forwarded_at' => now(),
            'received_by' => $admin->id,
            'received_at' => now(),
        ]);

        CommissionOpinion::create([
            'institution_id' => $institution->id,
            'proposition_id' => $prop1->id,
            'commission_id' => $ccj->id,
            'forwarding_id' => $forwarding1->id,
            'rapporteur_councilor_id' => $pedro->id,
            'result' => OpinionResult::FAVORAVEL->value,
            'text' => 'Parecer FAVORÁVEL. A matéria não apresenta vício de inconstitucionalidade '
                .'ou ilegalidade, atendendo aos requisitos regimentais.',
            'opinion_date' => now()->toDateString(),
            'registered_by' => $admin->id,
        ]);

        $forwarding1->update([
            'status' => ForwardingStatus::PARECER_FAVORAVEL,
            'completed_by' => $admin->id,
            'completed_at' => now(),
        ]);

        // --- Prop 2: aprovada, registrada, pautada e votada ----------------- //
        $prop2 = $make(PropositionModality::PROJETO_LEI,
            'Autoriza a abertura de crédito adicional especial no orçamento vigente',
            'Autoriza a abertura de crédito adicional especial no valor de R$ 250.000,00.',
            $ana, [$pedro]);
        $svc->submit($prop2, $admin);
        $svc->review($prop2, $secretaria, PropositionStatus::APROVADA,
            'Matéria apta à deliberação do Plenário.');
        $svc->registerOfficial($prop2, $secretaria);

        // --- Prop 3: aguardando análise ------------------------------------- //
        $prop3 = $make(PropositionModality::REQUERIMENTO,
            'Requer audiência pública sobre mobilidade urbana',
            'Requer a realização de audiência pública para debater mobilidade urbana.',
            $juliana);
        $svc->submit($prop3, $admin);

        // --- Prop 4: encaminhada à CFO aguardando parecer ------------------- //
        $prop4 = $make(PropositionModality::PROJETO_RESOLUCAO,
            'Cria o programa de transparência ativa da Câmara',
            'Institui o programa de transparência ativa e dados abertos da Câmara Municipal.',
            $pedro);
        $svc->submit($prop4, $admin);
        $svc->review($prop4, $secretaria, PropositionStatus::EM_ANALISE,
            'Encaminhada à Comissão de Finanças e Orçamento.');
        PropositionForwarding::create([
            'institution_id' => $institution->id,
            'proposition_id' => $prop4->id,
            'commission_id' => $cfo->id,
            'status' => ForwardingStatus::AGUARDANDO_PARECER,
            'deadline' => now()->addDays(30)->toDateString(),
            'notes' => 'Aguardando parecer da CFO sobre impacto orçamentário.',
            'forwarded_by' => $secretaria->id,
            'forwarded_at' => now(),
            'received_by' => $admin->id,
            'received_at' => now(),
        ]);

        // --- Prop 5: registrada, sem pauta, com encaminhamento pendente ----- //
        $prop5 = $make(PropositionModality::PROJETO_DECRETO_LEGISLATIVO,
            'Concede título de cidadão honorário',
            'Concede o título de cidadão honorário a personalidade de relevante atuação social.',
            $ana);
        $svc->submit($prop5, $admin);
        $svc->review($prop5, $secretaria, PropositionStatus::APROVADA, 'Mérito reconhecido.');
        $svc->registerOfficial($prop5, $secretaria);
        PropositionForwarding::create([
            'institution_id' => $institution->id,
            'proposition_id' => $prop5->id,
            'commission_id' => $ccj->id,
            'status' => ForwardingStatus::AGUARDANDO_RECEBIMENTO,
            'deadline' => now()->addDays(15)->toDateString(),
            'notes' => 'Redação final encaminhada para ciência da CCJ.',
            'forwarded_by' => $secretaria->id,
            'forwarded_at' => now(),
        ]);

        // --- Sessão legislativa com pauta ----------------------------------- //
        $session = LegislativeSession::create([
            'institution_id' => $institution->id,
            'number' => 12,
            'type' => SessionType::ORDINARIA->value,
            'date' => now()->toDateString(),
            'time' => '18:00',
            'location' => 'Plenário Principal',
            'president_councilor_id' => $carlos->id,
            'status' => SessionStatus::ENCERRADA->value,
            'notes' => 'Sessão ordinária com deliberação de matérias.',
        ]);

        $item = SessionAgendaItem::create([
            'institution_id' => $institution->id,
            'session_id' => $session->id,
            'proposition_id' => $prop2->id,
            'position' => 1,
            'appreciation_type' => AppreciationType::DISCUSSAO_E_VOTACAO->value,
            'will_discuss' => true,
            'will_vote' => true,
            'status' => AgendaItemStatus::APROVADA->value,
        ]);

        // Configuração de quórum da Câmara (maioria simples).
        QuorumSetting::persist($institution->id, PropositionModality::PROJETO_LEI->value, [
            'rule' => QuorumRule::MAIORIA_SIMPLES->value,
            'min_presence' => 3,
        ]);

        // --- Votação nominal apurada ---------------------------------------- //
        $voting = Voting::create([
            'institution_id' => $institution->id,
            'session_id' => $session->id,
            'session_agenda_item_id' => $item->id,
            'proposition_id' => $prop2->id,
            'turno' => 1,
            'type' => VotingType::NOMINAL->value,
            'quorum_rule' => QuorumRule::MAIORIA_SIMPLES->value,
            'quorum_min_presence' => 3,
            'held_at' => now(),
            'registered_by' => $admin->id,
        ]);

        $ballot = [
            $carlos->id => VoteType::FAVORAVEL->value,
            $ana->id => VoteType::FAVORAVEL->value,
            $pedro->id => VoteType::FAVORAVEL->value,
            $juliana->id => VoteType::CONTRARIO->value,
        ];

        foreach ($ballot as $councilorId => $vote) {
            VotingRecord::create([
                'institution_id' => $institution->id,
                'voting_id' => $voting->id,
                'councilor_id' => $councilorId,
                'vote' => $vote,
            ]);
        }

        $voting->recomputeCounts();
        $voting->update([
            'result' => VotingResult::APROVADA->value,
            'result_registered_at' => now(),
            'registered_by' => $admin->id,
        ]);

        // A aprovação em Plenário é o ato formal que altera a situação.
        $prop2->update(['status' => PropositionStatus::APROVADA]);
    }
}
