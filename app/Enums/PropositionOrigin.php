<?php

namespace App\Enums;

enum PropositionOrigin: string
{
    case LEGISLATIVO = 'legislativo';
    case EXECUTIVO = 'executivo';
    case OUTROS = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::LEGISLATIVO => 'Poder Legislativo',
            self::EXECUTIVO => 'Poder Executivo',
            self::OUTROS => 'Outros',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::LEGISLATIVO => 'bg-indigo-100 text-indigo-700',
            self::EXECUTIVO => 'bg-amber-100 text-amber-700',
            self::OUTROS => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Somente a origem legislativa exige um vereador como autor. Matérias de
     * origem externa (ex.: Projeto de Lei do Executivo) são identificadas pelo
     * nome/cargo do autor externo.
     */
    public function requiresCouncilorAuthor(): bool
    {
        return $this === self::LEGISLATIVO;
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
