<?php

namespace App\Enums;

enum CommissionType: string
{
    case PERMANENTE = 'permanente';
    case TEMPORARIA = 'temporaria';

    public function label(): string
    {
        return match ($this) {
            self::PERMANENTE => 'Permanente',
            self::TEMPORARIA => 'Temporária',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
