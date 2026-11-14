<?php

namespace App\Enums;

/**
 * Situação de uma divergência registrada sobre um protocolo.
 *
 * As situações finais (resolvida / encerrada sem resolução) encerram o
 * acompanhamento da ocorrência. O encerramento sem resolução exige
 * justificativa, registrada em observação, para fins de auditoria.
 */
enum DivergenceStatus: string
{
    case ABERTA = 'aberta';
    case EM_ANALISE = 'em_analise';
    case RESOLVIDA = 'resolvida';
    case ENCERRADA_SEM_RESOLUCAO = 'encerrada_sem_resolucao';

    public function label(): string
    {
        return match ($this) {
            self::ABERTA => 'Aberta',
            self::EM_ANALISE => 'Em análise',
            self::RESOLVIDA => 'Resolvida',
            self::ENCERRADA_SEM_RESOLUCAO => 'Encerrada sem resolução',
        };
    }

    /** Classes Tailwind para badge na interface. */
    public function badge(): string
    {
        return match ($this) {
            self::ABERTA => 'bg-rose-100 text-rose-700',
            self::EM_ANALISE => 'bg-amber-100 text-amber-700',
            self::RESOLVIDA => 'bg-emerald-100 text-emerald-700',
            self::ENCERRADA_SEM_RESOLUCAO => 'bg-slate-100 text-slate-700',
        };
    }

    /** Situações que encerram o acompanhamento da ocorrência. */
    public function isFinal(): bool
    {
        return in_array($this, [self::RESOLVIDA, self::ENCERRADA_SEM_RESOLUCAO], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
