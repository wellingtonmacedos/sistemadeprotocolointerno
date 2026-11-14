<?php

namespace App\Enums;

enum MovementStatus: string
{
    case AGUARDANDO_RECEBIMENTO = 'aguardando_recebimento';
    case RECEBIDO = 'recebido';
    case RECUSADO = 'recusado';

    public function label(): string
    {
        return match ($this) {
            self::AGUARDANDO_RECEBIMENTO => 'Aguardando recebimento',
            self::RECEBIDO => 'Recebido',
            self::RECUSADO => 'Recusado',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
