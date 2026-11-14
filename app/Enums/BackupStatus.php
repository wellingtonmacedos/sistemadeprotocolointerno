<?php

namespace App\Enums;

enum BackupStatus: string
{
    case NA_FILA = 'na_fila';
    case EM_EXECUCAO = 'em_execucao';
    case CONCLUIDO = 'concluido';
    case FALHOU = 'falhou';

    public function label(): string
    {
        return match ($this) {
            self::NA_FILA => 'Na fila',
            self::EM_EXECUCAO => 'Em execução',
            self::CONCLUIDO => 'Concluído',
            self::FALHOU => 'Falhou',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::NA_FILA => 'bg-slate-100 text-slate-700',
            self::EM_EXECUCAO => 'bg-sky-100 text-sky-700',
            self::CONCLUIDO => 'bg-emerald-100 text-emerald-700',
            self::FALHOU => 'bg-red-100 text-red-700',
        };
    }
}
