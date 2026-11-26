<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CommissionMemberController;
use App\Http\Controllers\CommissionOpinionController;
use App\Http\Controllers\LegislativeSessionController;
use App\Http\Controllers\Councilor\CouncilorDashboardController;
use App\Http\Controllers\CouncilorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\LegislativeFlowController;
use App\Http\Controllers\PostVotingActionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropositionController;
use App\Http\Controllers\PropositionForwardingController;
use App\Http\Controllers\QuorumSettingController;
use App\Http\Controllers\SessionAgendaItemController;
use App\Http\Controllers\VotingController;
use App\Http\Controllers\ProtocolActionController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\ProtocolDivergenceController;
use App\Http\Controllers\ProtocolMovementController;
use App\Http\Controllers\ProtocolMovementCorrectionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('painel') : redirect()->route('login');
});

// Autenticação
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // O limite por e-mail+IP é aplicado no controller; este limita por IP,
    // cobrindo tentativas que variam o e-mail informado.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:30,1')->name('login.attempt');

    // Recuperação de senha
    Route::get('/esqueci-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])->name('password.store');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'tenant'])->group(function () {

    Route::get('/painel', [DashboardController::class, 'index'])->name('painel');

    // Perfil do usuário
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('perfil.password');

    // Painel do Vereador
    Route::prefix('vereador')->name('councilor.')->group(function () {
        Route::get('/', [CouncilorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/proposicoes', [CouncilorDashboardController::class, 'propositions'])->name('propositions');
    });

    // Protocolos e Tramitação
    Route::prefix('protocolos')->name('protocols.')->group(function () {
        Route::get('/', [ProtocolController::class, 'index'])->middleware('permission:protocols.view')->name('index');
        Route::get('/caixa-de-entrada', [ProtocolController::class, 'inbox'])->middleware('permission:protocols.receive,protocols.receive_commission')->name('inbox');
        Route::get('/novo', [ProtocolController::class, 'create'])->middleware('permission:protocols.create')->name('create');
        Route::post('/', [ProtocolController::class, 'store'])->middleware('permission:protocols.create')->name('store');
        Route::get('/{protocol}', [ProtocolController::class, 'show'])->middleware('permission:protocols.view')->name('show');
        Route::get('/{protocol}/editar', [ProtocolController::class, 'edit'])->middleware('permission:protocols.edit')->name('edit');
        Route::put('/{protocol}', [ProtocolController::class, 'update'])->middleware('permission:protocols.edit')->name('update');
        Route::delete('/{protocol}', [ProtocolController::class, 'destroy'])->middleware('permission:protocols.delete')->name('destroy');

        Route::post('/{protocol}/encaminhar', [ProtocolMovementController::class, 'forward'])->middleware('permission:protocols.forward,protocols.dispatch_commission')->name('forward');
        Route::post('/{protocol}/andamento', [ProtocolMovementController::class, 'register'])->middleware('permission:protocols.receive,protocols.receive_commission')->name('movement');
        Route::post('/movimentos/{movement}/receber', [ProtocolMovementController::class, 'receive'])->middleware('permission:protocols.receive,protocols.receive_commission')->name('receive');
        Route::post('/movimentos/{movement}/receber-com-divergencia', [ProtocolMovementController::class, 'receiveWithDivergence'])->middleware('permission:protocols.receive,protocols.receive_commission')->name('receive_divergence');
        Route::post('/movimentos/{movement}/recusar', [ProtocolMovementController::class, 'refuse'])->middleware('permission:protocols.receive,protocols.receive_commission')->name('refuse');

        // Providências (FASE 1 — ações realizadas durante a tramitação)
        Route::post('/{protocol}/providencias', [ProtocolActionController::class, 'store'])->middleware('permission:protocols.actions.create')->name('actions.store');

        // Divergências (FASE 2 — ocorrências que não são movimentação)
        Route::post('/{protocol}/divergencias', [ProtocolDivergenceController::class, 'store'])->middleware('permission:protocols.actions.create')->name('divergences.store');
        Route::put('/divergencias/{divergence}', [ProtocolDivergenceController::class, 'update'])->middleware('permission:protocols.actions.manage')->name('divergences.update');

        // Retificações (FASE 2 — retificação não é exclusão)
        Route::post('/movimentos/{movement}/retificar', [ProtocolMovementCorrectionController::class, 'store'])->middleware('permission:protocols.movements.correct')->name('movements.correct');

        Route::post('/{protocol}/anexos', [ProtocolController::class, 'storeAttachment'])->middleware('permission:protocols.edit')->name('attachments.store');
        Route::get('/anexos/{attachment}/download', [ProtocolController::class, 'downloadAttachment'])->middleware('permission:protocols.view')->name('attachments.download');
        Route::delete('/anexos/{attachment}', [ProtocolController::class, 'destroyAttachment'])->middleware('permission:protocols.edit')->name('attachments.destroy');

        Route::get('/{protocol}/comprovante', [ProtocolController::class, 'receipt'])->middleware('permission:protocols.view')->name('receipt');
        Route::get('/{protocol}/espelho', [ProtocolController::class, 'mirror'])->middleware('permission:protocols.view')->name('mirror');
    });

    // Proposições Legislativas
    Route::prefix('proposicoes')->name('propositions.')->group(function () {
        Route::get('/', [PropositionController::class, 'index'])->middleware('permission:propositions.view')->name('index');
        Route::get('/nova', [PropositionController::class, 'create'])->middleware('permission:propositions.create')->name('create');
        Route::post('/', [PropositionController::class, 'store'])->middleware('permission:propositions.create')->name('store');
        Route::get('/{proposition}', [PropositionController::class, 'show'])->middleware('permission:propositions.view')->name('show');
        Route::get('/{proposition}/editar', [PropositionController::class, 'edit'])->middleware('permission:propositions.create')->name('edit');
        Route::put('/{proposition}', [PropositionController::class, 'update'])->middleware('permission:propositions.create')->name('update');
        Route::delete('/{proposition}', [PropositionController::class, 'destroy'])->middleware('permission:propositions.create')->name('destroy');

        Route::post('/{proposition}/enviar', [PropositionController::class, 'submit'])->middleware('permission:propositions.create')->name('submit');
        Route::post('/{proposition}/analisar', [PropositionController::class, 'review'])->middleware('permission:propositions.review')->name('review');
        Route::post('/{proposition}/registrar', [PropositionController::class, 'register'])->middleware('permission:propositions.register')->name('register');
        Route::post('/{proposition}/anexos', [PropositionController::class, 'storeAttachment'])->middleware('permission:propositions.create')->name('attachments.store');
        Route::get('/anexos/{attachment}/download', [PropositionController::class, 'downloadAttachment'])->middleware('permission:propositions.view')->name('attachments.download');
        Route::delete('/anexos/{attachment}', [PropositionController::class, 'destroyAttachment'])->middleware('permission:propositions.create')->name('attachments.destroy');
        Route::get('/{proposition}/documento', [PropositionController::class, 'document'])->middleware('permission:propositions.view')->name('document');

        // FASE 2 — Encaminhamentos para comissões e pareceres
        // Encaminhamento (criar/cancelar): exige a permissão de encaminhar.
        Route::post('/{proposition}/encaminhamentos', [PropositionForwardingController::class, 'store'])
            ->middleware('permission:propositions.forward')->name('forwardings.store');
        // Recebimento/situação: permissão OU membro ativo da comissão (ver controller).
        Route::post('/encaminhamentos/{forwarding}/receber', [PropositionForwardingController::class, 'receive'])
            ->name('forwardings.receive');
        Route::post('/encaminhamentos/{forwarding}/situacao', [PropositionForwardingController::class, 'updateStatus'])
            ->name('forwardings.status');
        Route::delete('/encaminhamentos/{forwarding}', [PropositionForwardingController::class, 'destroy'])
            ->middleware('permission:propositions.forward')->name('forwardings.destroy');
        // Parecer: permissão OU relator/presidente da comissão (ver controller).
        Route::post('/{proposition}/pareceres', [CommissionOpinionController::class, 'store'])
            ->name('opinions.store');
        Route::delete('/pareceres/{opinion}', [CommissionOpinionController::class, 'destroy'])
            ->name('opinions.destroy');

        // FASE 4 — Encaminhamentos pós-votação
        Route::post('/{proposition}/pos-votacao', [PostVotingActionController::class, 'store'])
            ->middleware('permission:votings.post_actions')->name('post_voting.store');
        Route::delete('/pos-votacao/{action}', [PostVotingActionController::class, 'destroy'])
            ->middleware('permission:votings.post_actions')->name('post_voting.destroy');
    });

    // Setores
    Route::resource('setores', SectorController::class)
        ->except(['show'])
        ->parameters(['setores' => 'sector'])
        ->names('sectors')
        ->middlewareFor(['index'], 'permission:sectors.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sectors.manage');

    // Vereadores
    Route::resource('vereadores', CouncilorController::class)
        ->except(['show'])
        ->parameters(['vereadores' => 'councilor'])
        ->names('councilors')
        ->middlewareFor(['index'], 'permission:councilors.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:councilors.manage');

    // Comissões Legislativas
    Route::prefix('comissoes')->name('commissions.')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->middleware('permission:commissions.view')->name('index');
        Route::get('/minhas', [CommissionController::class, 'mine'])->name('mine');
        Route::get('/nova', [CommissionController::class, 'create'])->middleware('permission:commissions.manage')->name('create');
        Route::post('/', [CommissionController::class, 'store'])->middleware('permission:commissions.manage')->name('store');
        Route::get('/{commission}', [CommissionController::class, 'show'])->middleware('permission:commissions.view')->name('show');
        Route::get('/{commission}/editar', [CommissionController::class, 'edit'])->middleware('permission:commissions.manage')->name('edit');
        Route::put('/{commission}', [CommissionController::class, 'update'])->middleware('permission:commissions.manage')->name('update');
        Route::delete('/{commission}', [CommissionController::class, 'destroy'])->middleware('permission:commissions.manage')->name('destroy');

        Route::post('/{commission}/integrantes', [CommissionMemberController::class, 'store'])
            ->middleware('permission:commissions.composition')->name('members.store');
        Route::put('/integrantes/{member}/encerrar', [CommissionMemberController::class, 'end'])
            ->middleware('permission:commissions.composition')->name('members.end');
        Route::delete('/integrantes/{member}', [CommissionMemberController::class, 'destroy'])
            ->middleware('permission:commissions.composition')->name('members.destroy');
    });

    // Fluxos Legislativos
    Route::prefix('fluxos-legislativos')->name('legislative-flows.')->group(function () {
        Route::get('/', [LegislativeFlowController::class, 'index'])->middleware('permission:legislative_flows.view')->name('index');
        Route::get('/novo', [LegislativeFlowController::class, 'create'])->middleware('permission:legislative_flows.manage')->name('create');
        Route::post('/', [LegislativeFlowController::class, 'store'])->middleware('permission:legislative_flows.manage')->name('store');
        Route::get('/{flow}', [LegislativeFlowController::class, 'show'])->middleware('permission:legislative_flows.view')->name('show');
        Route::delete('/{flow}', [LegislativeFlowController::class, 'destroy'])->middleware('permission:legislative_flows.manage')->name('destroy');
        Route::post('/{flow}/nova-versao', [LegislativeFlowController::class, 'newVersion'])->middleware('permission:legislative_flows.manage')->name('versions.store');
        Route::get('/{flow}/versoes/{version}/etapas', [LegislativeFlowController::class, 'editSteps'])->middleware('permission:legislative_flows.manage')->name('steps.edit');
        Route::put('/{flow}/versoes/{version}/etapas', [LegislativeFlowController::class, 'updateSteps'])->middleware('permission:legislative_flows.manage')->name('steps.update');
        Route::post('/{flow}/versoes/{version}/publicar', [LegislativeFlowController::class, 'publish'])->middleware('permission:legislative_flows.manage')->name('publish');
    });

    // Sessões Legislativas e Pauta
    Route::prefix('sessoes')->name('sessions.')->group(function () {
        Route::get('/', [LegislativeSessionController::class, 'index'])->middleware('permission:sessions.view')->name('index');
        Route::get('/criar', [LegislativeSessionController::class, 'create'])->middleware('permission:sessions.manage')->name('create');
        Route::post('/', [LegislativeSessionController::class, 'store'])->middleware('permission:sessions.manage')->name('store');
        Route::get('/{session}', [LegislativeSessionController::class, 'show'])->middleware('permission:sessions.view')->name('show');
        Route::get('/{session}/editar', [LegislativeSessionController::class, 'edit'])->middleware('permission:sessions.manage')->name('edit');
        Route::put('/{session}', [LegislativeSessionController::class, 'update'])->middleware('permission:sessions.manage')->name('update');
        Route::delete('/{session}', [LegislativeSessionController::class, 'destroy'])->middleware('permission:sessions.manage')->name('destroy');

        // Pauta / ordem do dia
        Route::post('/{session}/pauta', [SessionAgendaItemController::class, 'store'])->middleware('permission:sessions.agenda')->name('agenda.store');
        Route::post('/pauta/{item}/situacao', [SessionAgendaItemController::class, 'updateStatus'])->middleware('permission:sessions.agenda')->name('agenda.status');
        Route::post('/pauta/{item}/mover', [SessionAgendaItemController::class, 'move'])->middleware('permission:sessions.agenda')->name('agenda.move');
        Route::delete('/pauta/{item}', [SessionAgendaItemController::class, 'destroy'])->middleware('permission:sessions.agenda')->name('agenda.destroy');

        // Documentos da sessão
        Route::post('/{session}/documentos', [LegislativeSessionController::class, 'storeDocument'])->middleware('permission:sessions.manage')->name('documents.store');
        Route::get('/documentos/{document}/download', [LegislativeSessionController::class, 'downloadDocument'])->middleware('permission:sessions.view')->name('documents.download');
        Route::delete('/documentos/{document}', [LegislativeSessionController::class, 'destroyDocument'])->middleware('permission:sessions.manage')->name('documents.destroy');

        // Votações (FASE 4)
        Route::post('/{session}/votacoes', [VotingController::class, 'store'])->middleware('permission:votings.register')->name('votings.store');
        Route::post('/votacoes/{voting}/votos', [VotingController::class, 'recordVote'])->middleware('permission:votings.register')->name('votings.votes.store');
        Route::delete('/votacoes/votos/{record}', [VotingController::class, 'removeVote'])->middleware('permission:votings.register')->name('votings.votes.destroy');
        Route::post('/votacoes/{voting}/resultado', [VotingController::class, 'finalize'])->middleware('permission:votings.register')->name('votings.finalize');
        Route::delete('/votacoes/{voting}', [VotingController::class, 'destroy'])->middleware('permission:votings.register')->name('votings.destroy');
    });

    // Configuração de Quórum (FASE 4)
    Route::prefix('quorum')->name('quorum.')->group(function () {
        Route::get('/', [QuorumSettingController::class, 'index'])->middleware('permission:votings.quorum')->name('index');
        Route::post('/', [QuorumSettingController::class, 'store'])->middleware('permission:votings.quorum')->name('store');
        Route::delete('/{quorum}', [QuorumSettingController::class, 'destroy'])->middleware('permission:votings.quorum')->name('destroy');
    });

    // Usuários
    Route::resource('usuarios', UserController::class)
        ->except(['show'])
        ->parameters(['usuarios' => 'user'])
        ->names('users')
        ->middlewareFor(['index'], 'permission:users.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:users.manage');
    Route::post('/usuarios/{user}/bloquear', [UserController::class, 'toggleBlock'])
        ->middleware('permission:users.manage')->name('users.toggle-block');

    // Câmaras (Super Admin)
    Route::resource('camaras', InstitutionController::class)
        ->except(['show'])
        ->parameters(['camaras' => 'institution'])
        ->names('institutions')
        ->middleware('permission:institutions.manage');

    // Perfis e Permissões
    Route::get('/perfis', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/perfis/{role}/editar', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
    Route::put('/perfis/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');

    // Relatórios
    Route::prefix('relatorios')->name('reports.')->middleware('permission:reports.view')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/protocolos', [ReportController::class, 'protocols'])->name('protocols');
        Route::get('/proposicoes', [ReportController::class, 'propositions'])->name('propositions');
    });

    // Auditoria
    Route::get('/auditoria', [AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

    // Central de Backup e Restauração
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->middleware('permission:backups.view')->name('index');
        Route::post('/gerar', [BackupController::class, 'store'])->middleware('permission:backups.create')->name('store');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->middleware('permission:backups.download')->name('download');
        Route::post('/{backup}/restaurar', [BackupController::class, 'restore'])->middleware('permission:backups.restore')->name('restore');
        Route::post('/{backup}/reprocessar', [BackupController::class, 'retry'])->middleware('permission:backups.create')->name('retry');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->middleware('permission:backups.delete')->name('destroy');
        Route::get('/configuracoes', [BackupController::class, 'settings'])->middleware('permission:backups.settings')->name('settings');
        Route::put('/configuracoes', [BackupController::class, 'updateSettings'])->middleware('permission:backups.settings')->name('settings.update');
        Route::post('/restaurar-arquivo', [BackupController::class, 'uploadRestore'])->middleware('permission:backups.restore')->name('restore.upload');
    });
});
