<?php

namespace App\Enums;

enum PropositionStatus: string
{
    case RASCUNHO = 'rascunho';
    case ENVIADA = 'enviada';
    case EM_ANALISE = 'em_analise';
    case DEVOLVIDA = 'devolvida';
    case REENVIADA = 'reenviada';
    case APROVADA = 'aprovada';
    case REGISTRADA = 'registrada';
    case REJEITADA = 'rejeitada';
    case ARQUIVADA = 'arquivada';

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::ENVIADA => 'Enviada',
            self::EM_ANALISE => 'Em análise',
            self::DEVOLVIDA => 'Devolvida',
            self::REENVIADA => 'Reenviada',
            self::APROVADA => 'Aprovada',
            self::REGISTRADA => 'Registrada oficialmente',
            self::REJEITADA => 'Rejeitada',
            self::ARQUIVADA => 'Arquivada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::RASCUNHO => 'bg-slate-100 text-slate-700',
            self::ENVIADA => 'bg-sky-100 text-sky-700',
            self::EM_ANALISE => 'bg-indigo-100 text-indigo-700',
            self::DEVOLVIDA => 'bg-orange-100 text-orange-700',
            self::REENVIADA => 'bg-cyan-100 text-cyan-700',
            self::APROVADA => 'bg-teal-100 text-teal-700',
            self::REGISTRADA => 'bg-emerald-100 text-emerald-700',
            self::REJEITADA => 'bg-red-100 text-red-700',
            self::ARQUIVADA => 'bg-slate-200 text-slate-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
