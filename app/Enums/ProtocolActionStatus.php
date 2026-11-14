<?php

namespace App\Enums;

/**
 * Situação de uma providência registrada durante a tramitação.
 */
enum ProtocolActionStatus: string
{
    case REGISTRADA = 'registrada';
    case EM_ANDAMENTO = 'em_andamento';
    case CONCLUIDA = 'concluida';
    case CANCELADA = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::REGISTRADA => 'Registrada',
            self::EM_ANDAMENTO => 'Em andamento',
            self::CONCLUIDA => 'Concluída',
            self::CANCELADA => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::REGISTRADA => 'bg-slate-100 text-slate-700',
            self::EM_ANDAMENTO => 'bg-indigo-100 text-indigo-700',
            self::CONCLUIDA => 'bg-emerald-100 text-emerald-700',
            self::CANCELADA => 'bg-red-100 text-red-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
