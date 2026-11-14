<?php

namespace App\Enums;

enum LegislativeStepType: string
{
    case RECEBIMENTO = 'recebimento';
    case ANALISE = 'analise';
    case DESPACHO = 'despacho';
    case JURIDICO = 'juridico';
    case COMISSAO = 'comissao';
    case CONSOLIDACAO = 'consolidacao';
    case PAUTA = 'pauta';
    case PLENARIO = 'plenario';
    case VOTACAO = 'votacao';
    case RESULTADO = 'resultado';
    case REDACAO_FINAL = 'redacao_final';
    case SANCAO = 'sancao';
    case PUBLICACAO = 'publicacao';
    case CONCLUSAO = 'conclusao';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::RECEBIMENTO => 'Protocolo / Recebimento',
            self::ANALISE => 'Análise',
            self::DESPACHO => 'Despacho',
            self::JURIDICO => 'Análise Jurídica',
            self::COMISSAO => 'Análise por Comissão',
            self::CONSOLIDACAO => 'Consolidação de Pareceres',
            self::PAUTA => 'Inclusão na Pauta',
            self::PLENARIO => 'Discussão em Plenário',
            self::VOTACAO => 'Votação',
            self::RESULTADO => 'Registro do Resultado',
            self::REDACAO_FINAL => 'Redação Final',
            self::SANCAO => 'Sanção / Promulgação',
            self::PUBLICACAO => 'Publicação',
            self::CONCLUSAO => 'Conclusão / Arquivamento',
            self::OUTRO => 'Outro',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
