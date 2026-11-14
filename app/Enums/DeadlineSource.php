<?php

namespace App\Enums;

/**
 * Origem da definição do prazo: informada manualmente no encaminhamento ou
 * derivada de uma regra de fluxo.
 */
enum DeadlineSource: string
{
    case MANUAL = 'manual';
    case FLUXO = 'fluxo';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::FLUXO => 'Fluxo',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
