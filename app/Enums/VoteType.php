<?php

namespace App\Enums;

/**
 * Voto individual de um vereador (FASE 4).
 */
enum VoteType: string
{
    case FAVORAVEL = 'favoravel';
    case CONTRARIO = 'contrario';
    case ABSTENCAO = 'abstencao';
    case IMPEDIDO = 'impedido';
    case AUSENTE = 'ausente';

    public function label(): string
    {
        return match ($this) {
            self::FAVORAVEL => 'Favorável',
            self::CONTRARIO => 'Contrário',
            self::ABSTENCAO => 'Abstenção',
            self::IMPEDIDO => 'Impedido',
            self::AUSENTE => 'Ausente',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::FAVORAVEL => 'bg-emerald-100 text-emerald-700',
            self::CONTRARIO => 'bg-red-100 text-red-700',
            self::ABSTENCAO => 'bg-amber-100 text-amber-700',
            self::IMPEDIDO => 'bg-orange-100 text-orange-700',
            self::AUSENTE => 'bg-slate-200 text-slate-600',
        };
    }

    /** Votos válidos para apuração (favoráveis/contrários). */
    public function isCounted(): bool
    {
        return in_array($this, [self::FAVORAVEL, self::CONTRARIO], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
