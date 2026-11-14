<?php

namespace App\Enums;

/**
 * Natureza do prazo concedido a uma movimentação.
 * A ausência de prazo é representada por `null`, não por um valor deste enum.
 */
enum DeadlineType: string
{
    case ADMINISTRATIVO = 'administrativo';
    case REGIMENTAL = 'regimental';
    case DESPACHO = 'despacho';
    case EXCEPCIONAL = 'excepcional';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATIVO => 'Administrativo',
            self::REGIMENTAL => 'Regimental',
            self::DESPACHO => 'Por despacho',
            self::EXCEPCIONAL => 'Excepcional',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
