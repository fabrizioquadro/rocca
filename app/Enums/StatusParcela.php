<?php

namespace App\Enums;

enum StatusParcela: string
{
    case Aberta = 'aberta';
    case Parcial = 'parcial';
    case Paga = 'paga';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Parcial => 'Parcial',
            self::Paga => 'Paga',
        };
    }

    public function corBadge(): string
    {
        return match ($this) {
            self::Aberta => 'bg-label-warning',
            self::Parcial => 'bg-label-info',
            self::Paga => 'bg-label-success',
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
