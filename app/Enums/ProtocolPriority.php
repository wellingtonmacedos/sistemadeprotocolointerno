<?php

namespace App\Enums;

enum ProtocolPriority: string
{
    case BAIXA = 'baixa';
    case NORMAL = 'normal';
    case ALTA = 'alta';
    case URGENTE = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::BAIXA => 'Baixa',
            self::NORMAL => 'Normal',
            self::ALTA => 'Alta',
            self::URGENTE => 'Urgente',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::BAIXA => 'bg-slate-100 text-slate-700',
            self::NORMAL => 'bg-blue-100 text-blue-700',
            self::ALTA => 'bg-amber-100 text-amber-700',
            self::URGENTE => 'bg-red-100 text-red-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
