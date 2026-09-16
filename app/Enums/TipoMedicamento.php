<?php

namespace App\Enums;

enum TipoMedicamento: string
{
    case Ampola = 'ampola';
    case Miligrama = 'miligrama';
    case Procedimento = 'procedimento';

    public function label(): string
    {
        return match ($this) {
            self::Ampola => 'Ampola',
            self::Miligrama => 'Miligrama',
            self::Procedimento => 'Procedimento',
        };
    }

    /**
     * Exige informar o tamanho do vasilhame?
     */
    public function exigeVasilhame(): bool
    {
        return $this === self::Miligrama;
    }

    /**
     * Opções para popular selects/menus.
     *
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $tipo) => [$tipo->value => $tipo->label()])
            ->all();
    }
}
