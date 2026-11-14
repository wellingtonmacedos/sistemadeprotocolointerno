<?php

namespace App\Enums;

/**
 * Resultado de um parecer de comissão.
 *
 * Importante: parecer favorável NÃO equivale à aprovação da matéria pelo Plenário.
 */
enum OpinionResult: string
{
    case FAVORAVEL = 'favoravel';
    case CONTRARIO = 'contrario';
    case COM_RESSALVAS = 'com_ressalvas';

    public function label(): string
    {
        return match ($this) {
            self::FAVORAVEL => 'Favorável',
            self::CONTRARIO => 'Contrário',
            self::COM_RESSALVAS => 'Com ressalvas',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::FAVORAVEL => 'bg-emerald-100 text-emerald-700',
            self::CONTRARIO => 'bg-red-100 text-red-700',
            self::COM_RESSALVAS => 'bg-orange-100 text-orange-700',
        };
    }

    /** Situação correspondente do encaminhamento, quando aplicável. */
    public function forwardingStatus(): ForwardingStatus
    {
        return match ($this) {
            self::FAVORAVEL => ForwardingStatus::PARECER_FAVORAVEL,
            self::CONTRARIO => ForwardingStatus::PARECER_CONTRARIO,
            self::COM_RESSALVAS => ForwardingStatus::PARECER_COM_RESSALVAS,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
