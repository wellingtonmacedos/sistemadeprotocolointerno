<?php

namespace App\Enums;

enum MovementType: string
{
    case RECEBIMENTO_INICIAL = 'recebimento_inicial';
    case ENCAMINHAMENTO = 'encaminhamento';
    case RECEBIMENTO_SETOR = 'recebimento_setor';
    case DEVOLUCAO = 'devolucao';
    case SOLICITACAO_COMPLEMENTACAO = 'solicitacao_complementacao';
    case PARECER = 'parecer';
    case DESPACHO = 'despacho';
    case CONCLUSAO = 'conclusao';
    case ARQUIVAMENTO = 'arquivamento';

    public function label(): string
    {
        return match ($this) {
            self::RECEBIMENTO_INICIAL => 'Recebimento inicial',
            self::ENCAMINHAMENTO => 'Encaminhamento',
            self::RECEBIMENTO_SETOR => 'Recebimento pelo setor',
            self::DEVOLUCAO => 'Devolução',
            self::SOLICITACAO_COMPLEMENTACAO => 'Solicitação de complementação',
            self::PARECER => 'Parecer',
            self::DESPACHO => 'Despacho',
            self::CONCLUSAO => 'Conclusão',
            self::ARQUIVAMENTO => 'Arquivamento',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
