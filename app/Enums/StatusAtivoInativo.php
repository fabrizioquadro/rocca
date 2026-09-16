<?php

namespace App\Enums;

/**
 * Status genérico Ativo/Inativo, usado pelos cadastros do sistema.
 */
enum StatusAtivoInativo: string
{
    case Ativo = 'ativo';
    case Inativo = 'inativo';

    public function label(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Inativo => 'Inativo',
        };
    }

    /**
     * Opções para popular selects/menus.
     *
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
