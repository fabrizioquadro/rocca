<?php

namespace App\Enums;

enum StatusUsuario: string
{
    case Ativo = 'ativo';
    case Inativo = 'inativo';
    case Excluido = 'excluido';

    public function label(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Inativo => 'Inativo',
            self::Excluido => 'Excluído',
        };
    }
}
