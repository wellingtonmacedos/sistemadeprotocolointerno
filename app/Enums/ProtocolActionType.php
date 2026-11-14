<?php

namespace App\Enums;

/**
 * Tipo de PROVIDÊNCIA: ação realizada durante a tramitação documental.
 *
 * Não confundir com MovementType. Movimentações representam transferência de
 * responsabilidade; providências representam o que foi feito no documento.
 * Catálogo extensível — não espalhar strings fixas por controllers e views.
 */
enum ProtocolActionType: string
{
    case CONFERENCIA_DOCUMENTAL = 'conferencia_documental';
    case ANALISE_TECNICA = 'analise_tecnica';
    case ANALISE_JURIDICA = 'analise_juridica';
    case DESPACHO = 'despacho';
    case EMISSAO_PARECER = 'emissao_parecer';
    case SOLICITACAO_COMPLEMENTACAO = 'solicitacao_complementacao';
    case JUNTADA_DOCUMENTOS = 'juntada_documentos';
    case PREPARACAO_ENCAMINHAMENTO = 'preparacao_encaminhamento';
    case DEVOLUCAO_CORRECAO = 'devolucao_correcao';
    case REGISTRO_DECISAO_EXTERNA = 'registro_decisao_externa';
    case ARQUIVAMENTO = 'arquivamento';
    case OUTROS = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::CONFERENCIA_DOCUMENTAL => 'Conferência documental',
            self::ANALISE_TECNICA => 'Análise técnica',
            self::ANALISE_JURIDICA => 'Análise jurídica',
            self::DESPACHO => 'Despacho',
            self::EMISSAO_PARECER => 'Emissão de parecer',
            self::SOLICITACAO_COMPLEMENTACAO => 'Solicitação de complementação',
            self::JUNTADA_DOCUMENTOS => 'Juntada de documentos',
            self::PREPARACAO_ENCAMINHAMENTO => 'Preparação de encaminhamento',
            self::DEVOLUCAO_CORRECAO => 'Devolução para correção',
            self::REGISTRO_DECISAO_EXTERNA => 'Registro de decisão externa',
            self::ARQUIVAMENTO => 'Arquivamento',
            self::OUTROS => 'Outros',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
