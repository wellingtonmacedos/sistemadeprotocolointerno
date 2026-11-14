<?php

namespace App\Enums;

/**
 * Situação de uma movimentação em relação ao seu prazo.
 */
enum DeadlineSituation: string
{
    case SEM_PRAZO = 'sem_prazo';
    case NO_PRAZO = 'no_prazo';
    case PROXIMO_VENCIMENTO = 'proximo_vencimento';
    case VENCIDO = 'vencido';
    case CONCLUIDO_NO_PRAZO = 'concluido_no_prazo';
    case CONCLUIDO_APOS_PRAZO = 'concluido_apos_prazo';

    public function label(): string
    {
        return match ($this) {
            self::SEM_PRAZO => 'Sem prazo',
            self::NO_PRAZO => 'No prazo',
            self::PROXIMO_VENCIMENTO => 'Próximo do vencimento',
            self::VENCIDO => 'Vencido',
            self::CONCLUIDO_NO_PRAZO => 'Concluído no prazo',
            self::CONCLUIDO_APOS_PRAZO => 'Concluído após o prazo',
        };
    }

    /** Classes Tailwind para badge na interface. */
    public function badge(): string
    {
        return match ($this) {
            self::SEM_PRAZO => 'bg-slate-100 text-slate-700',
            self::NO_PRAZO => 'bg-emerald-100 text-emerald-700',
            self::PROXIMO_VENCIMENTO => 'bg-amber-100 text-amber-700',
            self::VENCIDO => 'bg-red-100 text-red-700',
            self::CONCLUIDO_NO_PRAZO => 'bg-sky-100 text-sky-700',
            self::CONCLUIDO_APOS_PRAZO => 'bg-orange-100 text-orange-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
