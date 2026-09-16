<?php

namespace App\Enums;

enum TipoMovimentacaoEstoque: string
{
    case Entrada = 'entrada';
    case Estorno = 'estorno';
    case Baixa = 'baixa';
    case Transferencia = 'transferencia';
    case Consumo = 'consumo';
    case Ajuste = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Estorno => 'Estorno',
            self::Baixa => 'Baixa',
            self::Transferencia => 'Transferência',
            self::Consumo => 'Consumo',
            self::Ajuste => 'Ajuste',
        };
    }

    /**
     * Classe de badge do template para exibir o tipo.
     */
    public function corBadge(): string
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Estorno => 'danger',
            self::Ajuste => 'info',
            default => 'warning',
        };
    }
}
