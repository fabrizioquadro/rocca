<?php

namespace App\Enums;

enum StatusSemana: string
{
    case Agendada = 'agendada';
    case FilaAplicacao = 'fila_aplicacao';
    case Atendimento = 'atendimento';
    case Aplicada = 'aplicada';
    case AplicacaoParcial = 'aplicacao_parcial';

    public function label(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::FilaAplicacao => 'Fila de Aplicação',
            self::Atendimento => 'Atendimento',
            self::Aplicada => 'Aplicada',
            self::AplicacaoParcial => 'Aplicação Parcial',
        };
    }

    public function corBadge(): string
    {
        return match ($this) {
            self::Agendada => 'bg-label-primary',
            self::FilaAplicacao => 'bg-label-info',
            self::Atendimento => 'bg-label-warning',
            self::Aplicada => 'bg-label-success',
            self::AplicacaoParcial => 'bg-label-danger',
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
