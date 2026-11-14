<?php

namespace App\Enums;

enum CommissionMemberRole: string
{
    case PRESIDENTE = 'presidente';
    case VICE_PRESIDENTE = 'vice_presidente';
    case SECRETARIO = 'secretario';
    case MEMBRO = 'membro';
    case RELATOR = 'relator';

    public function label(): string
    {
        return match ($this) {
            self::PRESIDENTE => 'Presidente',
            self::VICE_PRESIDENTE => 'Vice-presidente',
            self::SECRETARIO => 'Secretário',
            self::MEMBRO => 'Membro',
            self::RELATOR => 'Relator',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
