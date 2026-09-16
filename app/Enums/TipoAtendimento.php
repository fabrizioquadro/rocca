<?php

namespace App\Enums;

enum TipoAtendimento: string
{
    case ConsultaTratamento = 'consulta_tratamento';
    case ConsultaNova = 'consulta_nova';
    case Retorno = 'retorno';
    case ColetaBio = 'coleta_bio';
    case Implante = 'implante';

    public function label(): string
    {
        return match ($this) {
            self::ConsultaTratamento => 'Consulta tratamento',
            self::ConsultaNova => 'Consulta nova',
            self::Retorno => 'Retorno',
            self::ColetaBio => 'Coleta/Bio',
            self::Implante => 'Implante',
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
            ->mapWithKeys(fn (self $tipo) => [$tipo->value => $tipo->label()])
            ->all();
    }
}
