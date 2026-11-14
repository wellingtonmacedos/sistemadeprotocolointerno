<?php

namespace App\Enums;

enum DocumentType: string
{
    case OFICIO = 'oficio';
    case REQUERIMENTO = 'requerimento';
    case SOLICITACAO = 'solicitacao';
    case CORRESPONDENCIA = 'correspondencia';
    case DOCUMENTO_EXTERNO = 'documento_externo';
    case OUTROS = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::OFICIO => 'Ofício',
            self::REQUERIMENTO => 'Requerimento',
            self::SOLICITACAO => 'Solicitação',
            self::CORRESPONDENCIA => 'Correspondência',
            self::DOCUMENTO_EXTERNO => 'Documento Externo',
            self::OUTROS => 'Outros',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
