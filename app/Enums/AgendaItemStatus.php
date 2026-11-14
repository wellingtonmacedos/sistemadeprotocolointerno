<?php

namespace App\Enums;

/**
 * Situação de um item da pauta (ordem do dia).
 *
 * A inclusão na pauta NÃO aprova automaticamente a proposição.
 */
enum AgendaItemStatus: string
{
    case PAUTADA = 'pautada';
    case EM_DISCUSSAO = 'em_discussao';
    case EM_VOTACAO = 'em_votacao';
    case APROVADA = 'aprovada';
    case REJEITADA = 'rejeitada';
    case ADIADA = 'adiada';
    case RETIRADA = 'retirada';
    case NAO_APRECIADA = 'nao_apreciada';

    public function label(): string
    {
        return match ($this) {
            self::PAUTADA => 'Pautada',
            self::EM_DISCUSSAO => 'Em discussão',
            self::EM_VOTACAO => 'Em votação',
            self::APROVADA => 'Aprovada',
            self::REJEITADA => 'Rejeitada',
            self::ADIADA => 'Adiada',
            self::RETIRADA => 'Retirada',
            self::NAO_APRECIADA => 'Não apreciada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PAUTADA => 'bg-sky-100 text-sky-700',
            self::EM_DISCUSSAO => 'bg-indigo-100 text-indigo-700',
            self::EM_VOTACAO => 'bg-amber-100 text-amber-700',
            self::APROVADA => 'bg-emerald-100 text-emerald-700',
            self::REJEITADA => 'bg-red-100 text-red-700',
            self::ADIADA => 'bg-orange-100 text-orange-700',
            self::RETIRADA => 'bg-rose-100 text-rose-700',
            self::NAO_APRECIADA => 'bg-slate-200 text-slate-600',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::APROVADA, self::REJEITADA, self::RETIRADA], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
