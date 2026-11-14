<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMINISTRADOR = 'administrador';
    case SECRETARIA_DIRETORIA = 'secretaria_diretoria';
    case RECEPCAO_PROTOCOLO = 'recepcao_protocolo';
    case SERVIDOR_SETOR = 'servidor_setor';
    case PRESIDENTE = 'presidente';
    case VEREADOR = 'vereador';
    case SECRETARIA_LEGISLATIVA = 'secretaria_legislativa';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMINISTRADOR => 'Administrador da Câmara',
            self::SECRETARIA_DIRETORIA => 'Secretaria / Diretoria',
            self::RECEPCAO_PROTOCOLO => 'Recepção / Protocolo',
            self::SERVIDOR_SETOR => 'Servidor de Setor',
            self::PRESIDENTE => 'Presidente',
            self::VEREADOR => 'Vereador',
            self::SECRETARIA_LEGISLATIVA => 'Secretaria Legislativa',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
