<?php

namespace App\Enums;

enum SessionStatus: string
{
    case AGENDADA = 'agendada';
    case EM_ANDAMENTO = 'em_andamento';
    case ENCERRADA = 'encerrada';
    case CANCELADA = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::AGENDADA => 'Agendada',
            self::EM_ANDAMENTO => 'Em andamento',
            self::ENCERRADA => 'Encerrada',
            self::CANCELADA => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::AGENDADA => 'bg-sky-100 text-sky-700',
            self::EM_ANDAMENTO => 'bg-amber-100 text-amber-700',
            self::ENCERRADA => 'bg-emerald-100 text-emerald-700',
            self::CANCELADA => 'bg-red-100 text-red-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
