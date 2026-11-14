<?php

namespace App\Enums;

enum BackupType: string
{
    case INSTITUTION = 'institution';
    case PLATFORM = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::INSTITUTION => 'Backup da Câmara',
            self::PLATFORM => 'Backup da Plataforma',
        };
    }
}
