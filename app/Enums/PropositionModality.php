<?php

namespace App\Enums;

enum PropositionModality: string
{
    case PROJETO_LEI = 'projeto_lei';
    case PROJETO_RESOLUCAO = 'projeto_resolucao';
    case PROJETO_DECRETO_LEGISLATIVO = 'projeto_decreto_legislativo';
    case VETO = 'veto';
    case INDICACAO = 'indicacao';
    case MOCAO = 'mocao';
    case REQUERIMENTO = 'requerimento';

    public function label(): string
    {
        return match ($this) {
            self::PROJETO_LEI => 'Projeto de Lei',
            self::PROJETO_RESOLUCAO => 'Projeto de Resolução',
            self::PROJETO_DECRETO_LEGISLATIVO => 'Projeto de Decreto Legislativo',
            self::VETO => 'Veto',
            self::INDICACAO => 'Indicação',
            self::MOCAO => 'Moção',
            self::REQUERIMENTO => 'Requerimento',
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::PROJETO_LEI => 'PL',
            self::PROJETO_RESOLUCAO => 'PR',
            self::PROJETO_DECRETO_LEGISLATIVO => 'PDL',
            self::VETO => 'VET',
            self::INDICACAO => 'IND',
            self::MOCAO => 'MOC',
            self::REQUERIMENTO => 'REQ',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
