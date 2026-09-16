<?php

namespace App\Enums;

enum StatusSemanaItem: string
{
    case Aberto = 'aberto';
    case Aplicado = 'aplicado';
    case Pendente = 'pendente';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Aplicado => 'Aplicado',
            self::Pendente => 'Pendente',
        };
    }

    public function corBadge(): string
    {
        return match ($this) {
            self::Aberto => 'bg-label-primary',
            self::Aplicado => 'bg-label-success',
            self::Pendente => 'bg-label-warning',
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
