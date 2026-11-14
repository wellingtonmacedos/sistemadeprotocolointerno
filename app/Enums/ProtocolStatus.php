<?php

namespace App\Enums;

enum ProtocolStatus: string
{
    case AGUARDANDO_RECEBIMENTO = 'aguardando_recebimento';
    case RECEBIDO = 'recebido';
    case EM_ANALISE = 'em_analise';
    case AGUARDANDO_PROVIDENCIAS = 'aguardando_providencias';
    case ENCAMINHADO = 'encaminhado';
    case CONCLUIDO = 'concluido';
    case ARQUIVADO = 'arquivado';

    public function label(): string
    {
        return match ($this) {
            self::AGUARDANDO_RECEBIMENTO => 'Aguardando recebimento',
            self::RECEBIDO => 'Recebido',
            self::EM_ANALISE => 'Em análise',
            self::AGUARDANDO_PROVIDENCIAS => 'Aguardando providências',
            self::ENCAMINHADO => 'Encaminhado',
            self::CONCLUIDO => 'Concluído',
            self::ARQUIVADO => 'Arquivado',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::AGUARDANDO_RECEBIMENTO => 'bg-amber-100 text-amber-700',
            self::RECEBIDO => 'bg-sky-100 text-sky-700',
            self::EM_ANALISE => 'bg-indigo-100 text-indigo-700',
            self::AGUARDANDO_PROVIDENCIAS => 'bg-orange-100 text-orange-700',
            self::ENCAMINHADO => 'bg-blue-100 text-blue-700',
            self::CONCLUIDO => 'bg-emerald-100 text-emerald-700',
            self::ARQUIVADO => 'bg-slate-200 text-slate-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
