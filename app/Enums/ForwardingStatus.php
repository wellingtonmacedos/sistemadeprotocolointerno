<?php

namespace App\Enums;

/**
 * Situação de um encaminhamento de proposição para comissão.
 */
enum ForwardingStatus: string
{
    case AGUARDANDO_RECEBIMENTO = 'aguardando_recebimento';
    case EM_ANALISE = 'em_analise';
    case AGUARDANDO_PARECER = 'aguardando_parecer';
    case PARECER_FAVORAVEL = 'parecer_favoravel';
    case PARECER_CONTRARIO = 'parecer_contrario';
    case PARECER_COM_RESSALVAS = 'parecer_com_ressalvas';
    case DEVOLVIDA_AJUSTES = 'devolvida_para_ajustes';
    case CONCLUIDA = 'concluida';

    public function label(): string
    {
        return match ($this) {
            self::AGUARDANDO_RECEBIMENTO => 'Aguardando recebimento',
            self::EM_ANALISE => 'Em análise',
            self::AGUARDANDO_PARECER => 'Aguardando parecer',
            self::PARECER_FAVORAVEL => 'Parecer favorável',
            self::PARECER_CONTRARIO => 'Parecer contrário',
            self::PARECER_COM_RESSALVAS => 'Parecer com ressalvas',
            self::DEVOLVIDA_AJUSTES => 'Devolvida para ajustes',
            self::CONCLUIDA => 'Concluída',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::AGUARDANDO_RECEBIMENTO => 'bg-amber-100 text-amber-700',
            self::EM_ANALISE => 'bg-indigo-100 text-indigo-700',
            self::AGUARDANDO_PARECER => 'bg-sky-100 text-sky-700',
            self::PARECER_FAVORAVEL => 'bg-emerald-100 text-emerald-700',
            self::PARECER_CONTRARIO => 'bg-red-100 text-red-700',
            self::PARECER_COM_RESSALVAS => 'bg-orange-100 text-orange-700',
            self::DEVOLVIDA_AJUSTES => 'bg-rose-100 text-rose-700',
            self::CONCLUIDA => 'bg-slate-200 text-slate-700',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::CONCLUIDA], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    /** Situações que podem ser definidas manualmente durante a análise. */
    public static function manualOptions(): array
    {
        return collect([
            self::EM_ANALISE,
            self::AGUARDANDO_PARECER,
            self::DEVOLVIDA_AJUSTES,
            self::CONCLUIDA,
        ])->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
