<?php

namespace App\Enums;

enum AppreciationType: string
{
    case LEITURA = 'leitura';
    case DISCUSSAO_E_VOTACAO = 'discussao_e_votacao';
    case DISCUSSAO = 'discussao';
    case VOTACAO = 'votacao';
    case REDACAO_FINAL = 'redacao_final';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::LEITURA => 'Leitura',
            self::DISCUSSAO_E_VOTACAO => 'Discussão e votação',
            self::DISCUSSAO => 'Somente discussão',
            self::VOTACAO => 'Somente votação',
            self::REDACAO_FINAL => 'Redação final',
            self::OUTRO => 'Outro',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
