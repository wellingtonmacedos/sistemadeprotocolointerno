<?php

namespace App\Enums;

/**
 * Resultado formal de uma votação (FASE 4).
 *
 * Importante: a proposição só é considerada aprovada por ato formal do Plenário,
 * nunca pela mera inclusão na pauta.
 */
enum VotingResult: string
{
    case APROVADA = 'aprovada';
    case REJEITADA = 'rejeitada';
    case APROVADA_COM_EMENDAS = 'aprovada_com_emendas';
    case ADIADA = 'adiada';
    case RETIRADA_DE_PAUTA = 'retirada_de_pauta';
    case VISTA_CONCEDIDA = 'vista_concedida';
    case NAO_REALIZADA = 'nao_realizada';
    case OUTROS = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::APROVADA => 'Aprovada',
            self::REJEITADA => 'Rejeitada',
            self::APROVADA_COM_EMENDAS => 'Aprovada com emendas',
            self::ADIADA => 'Adiada',
            self::RETIRADA_DE_PAUTA => 'Retirada de pauta',
            self::VISTA_CONCEDIDA => 'Vista concedida',
            self::NAO_REALIZADA => 'Não realizada',
            self::OUTROS => 'Outros',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::APROVADA => 'bg-emerald-100 text-emerald-700',
            self::REJEITADA => 'bg-red-100 text-red-700',
            self::APROVADA_COM_EMENDAS => 'bg-teal-100 text-teal-700',
            self::ADIADA => 'bg-orange-100 text-orange-700',
            self::RETIRADA_DE_PAUTA => 'bg-rose-100 text-rose-700',
            self::VISTA_CONCEDIDA => 'bg-amber-100 text-amber-700',
            self::NAO_REALIZADA => 'bg-slate-200 text-slate-600',
            self::OUTROS => 'bg-slate-100 text-slate-600',
        };
    }

    public function isApproval(): bool
    {
        return in_array($this, [self::APROVADA, self::APROVADA_COM_EMENDAS], true);
    }

    /** Resultados que exigem validação contra a regra de quórum configurada. */
    public function requiresQuorumCheck(): bool
    {
        return in_array($this, [self::APROVADA, self::APROVADA_COM_EMENDAS, self::REJEITADA], true);
    }

    /** Situação correspondente na pauta (ordem do dia). */
    public function agendaItemStatus(): AgendaItemStatus
    {
        return match ($this) {
            self::APROVADA, self::APROVADA_COM_EMENDAS => AgendaItemStatus::APROVADA,
            self::REJEITADA => AgendaItemStatus::REJEITADA,
            self::ADIADA, self::VISTA_CONCEDIDA => AgendaItemStatus::ADIADA,
            self::RETIRADA_DE_PAUTA => AgendaItemStatus::RETIRADA,
            self::NAO_REALIZADA, self::OUTROS => AgendaItemStatus::NAO_APRECIADA,
        };
    }

    /** Situação correspondente da proposição (null = não alterar). */
    public function propositionStatus(): ?PropositionStatus
    {
        return match ($this) {
            self::APROVADA, self::APROVADA_COM_EMENDAS => PropositionStatus::APROVADA,
            self::REJEITADA => PropositionStatus::REJEITADA,
            default => null,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
