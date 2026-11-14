<?php

namespace App\Enums;

/**
 * Situações e encaminhamentos pós-votação (FASE 4).
 *
 * Uma proposição aprovada em Plenário NÃO se transforma automaticamente em lei:
 * as etapas posteriores respeitam a modalidade e o fluxo configurado.
 */
enum PostVotingStatus: string
{
    case APROVADA_PLENARIO = 'aprovada_plenario';
    case AGUARDANDO_REDACAO_FINAL = 'aguardando_redacao_final';
    case REDACAO_FINAL_CONCLUIDA = 'redacao_final_concluida';
    case AUTOGRAFO_REGISTRADO = 'autografo_registrado';
    case ENCAMINHADA_SANCAO = 'encaminhada_sancao';
    case SANCIONADA = 'sancionada';
    case VETADA = 'vetada';
    case PROMULGADA = 'promulgada';
    case PUBLICADA = 'publicada';
    case REJEITADA = 'rejeitada';
    case CONCLUIDA = 'concluida';
    case ARQUIVADA = 'arquivada';

    public function label(): string
    {
        return match ($this) {
            self::APROVADA_PLENARIO => 'Aprovada em Plenário',
            self::AGUARDANDO_REDACAO_FINAL => 'Aguardando redação final',
            self::REDACAO_FINAL_CONCLUIDA => 'Redação final concluída',
            self::AUTOGRAFO_REGISTRADO => 'Autógrafo registrado',
            self::ENCAMINHADA_SANCAO => 'Encaminhada para sanção',
            self::SANCIONADA => 'Sancionada',
            self::VETADA => 'Vetada',
            self::PROMULGADA => 'Promulgada',
            self::PUBLICADA => 'Publicada',
            self::REJEITADA => 'Rejeitada',
            self::CONCLUIDA => 'Concluída',
            self::ARQUIVADA => 'Arquivada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::APROVADA_PLENARIO,
            self::REDACAO_FINAL_CONCLUIDA,
            self::AUTOGRAFO_REGISTRADO => 'bg-teal-100 text-teal-700',
            self::AGUARDANDO_REDACAO_FINAL,
            self::ENCAMINHADA_SANCAO => 'bg-sky-100 text-sky-700',
            self::SANCIONADA,
            self::PROMULGADA,
            self::PUBLICADA => 'bg-emerald-100 text-emerald-700',
            self::VETADA,
            self::REJEITADA => 'bg-red-100 text-red-700',
            self::CONCLUIDA => 'bg-indigo-100 text-indigo-700',
            self::ARQUIVADA => 'bg-slate-200 text-slate-700',
        };
    }

    /** Situação correspondente da proposição (null = não alterar). */
    public function propositionStatus(): ?PropositionStatus
    {
        return match ($this) {
            self::ARQUIVADA => PropositionStatus::ARQUIVADA,
            default => null,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::CONCLUIDA, self::ARQUIVADA], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
