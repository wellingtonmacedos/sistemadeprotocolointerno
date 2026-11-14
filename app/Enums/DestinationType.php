<?php

namespace App\Enums;

/**
 * Tipo de destino de uma movimentação de protocolo.
 * Setores e comissões são destinos institucionais independentes.
 */
enum DestinationType: string
{
    case SECTOR = 'sector';
    case COMMISSION = 'commission';

    public function label(): string
    {
        return match ($this) {
            self::SECTOR => 'Setor',
            self::COMMISSION => 'Comissão',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
