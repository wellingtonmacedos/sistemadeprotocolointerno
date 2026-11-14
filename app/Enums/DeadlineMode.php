<?php

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Modo de contagem do prazo.
 *
 * Nesta fase não há simulação de feriados/calendário institucional: o modo
 * "dias úteis" apenas desconsidera sábados e domingos. A estrutura fica
 * preparada para incorporar um calendário institucional em fase futura.
 */
enum DeadlineMode: string
{
    case CORRIDOS = 'corridos';
    case UTEIS = 'uteis';

    public function label(): string
    {
        return match ($this) {
            self::CORRIDOS => 'Dias corridos',
            self::UTEIS => 'Dias úteis',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    /** Data-limite resultante de `$days` dias a partir de `$start`. */
    public function addTo(CarbonInterface $start, int $days): Carbon
    {
        $base = Carbon::parse($start);

        return $this === self::UTEIS
            ? $base->copy()->addWeekdays($days)
            : $base->copy()->addDays($days);
    }
}
