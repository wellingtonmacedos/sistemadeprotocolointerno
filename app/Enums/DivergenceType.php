<?php

namespace App\Enums;

/**
 * Tipo de DIVERGÊNCIA: ocorrência registrada sobre um protocolo, durante ou
 * após o recebimento.
 *
 * DIVERGÊNCIA NÃO É MOVIMENTAÇÃO: é uma ocorrência que precisa ser acompanhada.
 * Registrar uma divergência não cria movimentação, não marca recebimento e não
 * altera a responsabilidade atual do protocolo. Catálogo extensível — não
 * espalhar strings fixas por controllers e views.
 */
enum DivergenceType: string
{
    case ANEXO_FALTANTE = 'anexo_faltante';
    case QUANTIDADE_PAGINAS = 'quantidade_paginas';
    case INFORMACAO_INCONSISTENTE = 'informacao_inconsistente';
    case RECEBIDO_COM_RESSALVA = 'recebido_com_ressalva';
    case IDENTIFICACAO_INTERESSADO = 'identificacao_interessado';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::ANEXO_FALTANTE => 'Anexo faltante',
            self::QUANTIDADE_PAGINAS => 'Divergência na quantidade de páginas',
            self::INFORMACAO_INCONSISTENTE => 'Informação inconsistente',
            self::RECEBIDO_COM_RESSALVA => 'Recebido com ressalva',
            self::IDENTIFICACAO_INTERESSADO => 'Problema na identificação do interessado',
            self::OUTRO => 'Outro',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
