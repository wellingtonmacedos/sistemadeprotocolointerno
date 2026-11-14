<?php

namespace App\Enums;

/**
 * Modalidade de votação em Plenário (FASE 4).
 */
enum VotingType: string
{
    case NOMINAL = 'nominal';
    case SIMBOLICA = 'simbolica';
    case SECRETA = 'secreta';

    public function label(): string
    {
        return match ($this) {
            self::NOMINAL => 'Nominal',
            self::SIMBOLICA => 'Simbólica',
            self::SECRETA => 'Secreta',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::NOMINAL => 'bg-indigo-100 text-indigo-700',
            self::SIMBOLICA => 'bg-sky-100 text-sky-700',
            self::SECRETA => 'bg-slate-200 text-slate-700',
        };
    }

    /** Exige registro individual do voto de cada vereador? */
    public function requiresIndividualVotes(): bool
    {
        return in_array($this, [self::NOMINAL, self::SECRETA], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
