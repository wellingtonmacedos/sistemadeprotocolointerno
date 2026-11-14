<?php

namespace App\Enums;

enum SessionType: string
{
    case ORDINARIA = 'ordinaria';
    case EXTRAORDINARIA = 'extraordinaria';
    case SOLENE = 'solene';
    case ESPECIAL = 'especial';

    public function label(): string
    {
        return match ($this) {
            self::ORDINARIA => 'Ordinária',
            self::EXTRAORDINARIA => 'Extraordinária',
            self::SOLENE => 'Solene',
            self::ESPECIAL => 'Especial',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
