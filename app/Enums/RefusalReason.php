<?php

namespace App\Enums;

/**
 * Motivo formal de recusa de recebimento de uma movimentação.
 * A recusa não é recebimento: a responsabilidade permanece com o remetente.
 */
enum RefusalReason: string
{
    case DESTINO_INCORRETO = 'destino_incorreto';
    case DOCUMENTO_INCOMPLETO = 'documento_incompleto';
    case DOCUMENTACAO_OBRIGATORIA_AUSENTE = 'documentacao_obrigatoria_ausente';
    case DIVERGENCIA_IDENTIFICACAO = 'divergencia_identificacao';
    case ENCAMINHAMENTO_INDEVIDO = 'encaminhamento_indevido';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::DESTINO_INCORRETO => 'Destino incorreto',
            self::DOCUMENTO_INCOMPLETO => 'Documento incompleto',
            self::DOCUMENTACAO_OBRIGATORIA_AUSENTE => 'Ausência de documentação obrigatória',
            self::DIVERGENCIA_IDENTIFICACAO => 'Divergência de identificação',
            self::ENCAMINHAMENTO_INDEVIDO => 'Encaminhamento indevido',
            self::OUTRO => 'Outro motivo',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
