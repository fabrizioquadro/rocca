<?php

namespace App\Enums;

enum TipoUsuario: string
{
    case Administrador = 'administrador';
    case Secretaria = 'secretaria';
    case Enfermagem = 'enfermagem';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Secretaria => 'Secretaria',
            self::Enfermagem => 'Enfermagem',
        };
    }
}
