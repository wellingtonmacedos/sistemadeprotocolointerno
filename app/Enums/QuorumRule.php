<?php

namespace App\Enums;

/**
 * Regra de quórum configurável por instituição e modalidade (FASE 4).
 *
 * Não existe quórum universal: cada Câmara configura a regra aplicável.
 */
enum QuorumRule: string
{
    case MAIORIA_SIMPLES = 'maioria_simples';
    case MAIORIA_ABSOLUTA = 'maioria_absoluta';
    case DOIS_TERCOS = 'dois_tercos';
    case TRES_QUINTOS = 'tres_quintos';
    case UNANIMIDADE = 'unanimidade';

    public function label(): string
    {
        return match ($this) {
            self::MAIORIA_SIMPLES => 'Maioria simples',
            self::MAIORIA_ABSOLUTA => 'Maioria absoluta',
            self::DOIS_TERCOS => 'Dois terços',
            self::TRES_QUINTOS => 'Três quintos',
            self::UNANIMIDADE => 'Unanimidade',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MAIORIA_SIMPLES => 'Mais votos favoráveis do que contrários.',
            self::MAIORIA_ABSOLUTA => 'Favoráveis superiores à metade dos presentes.',
            self::DOIS_TERCOS => 'Favoráveis iguais ou superiores a 2/3 dos presentes.',
            self::TRES_QUINTOS => 'Favoráveis iguais ou superiores a 3/5 dos presentes.',
            self::UNANIMIDADE => 'Todos os presentes votam favoravelmente.',
        };
    }

    /**
     * Decide se a matéria foi aprovada conforme a regra, dados os totais.
     *
     * @param  array{favor:int,contra:int,abstencao:int,impedido:int}  $counts
     * @param  int  $present  total de vereadores presentes
     */
    public function approves(array $counts, int $present): bool
    {
        $favor = $counts['favor'];
        $contra = $counts['contra'];
        $abstencao = $counts['abstencao'];

        if ($present <= 0) {
            return false;
        }

        return match ($this) {
            self::MAIORIA_SIMPLES => $favor > $contra,
            self::MAIORIA_ABSOLUTA => $favor * 2 > $present,
            self::DOIS_TERCOS => $favor * 3 >= $present * 2,
            self::TRES_QUINTOS => $favor * 5 >= $present * 3,
            self::UNANIMIDADE => $contra === 0 && $abstencao === 0 && $favor === $present,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
