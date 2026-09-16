<?php

namespace App\Enums;

enum FormaPagamento: string
{
    case Dinheiro = 'dinheiro';
    case CartaoCredito = 'cartao_credito';
    case CartaoDebito = 'cartao_debito';
    case Pix = 'pix';
    case LinkPagamento = 'link_pagamento';

    /**
     * Máximo de parcelas para cartão de crédito / link de pagamento.
     */
    public const MAX_PARCELAS = 12;

    public function label(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::CartaoCredito => 'Cartão Crédito',
            self::CartaoDebito => 'Cartão Débito',
            self::Pix => 'Pix',
            self::LinkPagamento => 'Link Pagamento',
        };
    }

    public function corBadge(): string
    {
        return match ($this) {
            self::Dinheiro => 'bg-label-success',
            self::CartaoCredito => 'bg-label-primary',
            self::CartaoDebito => 'bg-label-info',
            self::Pix => 'bg-label-warning',
            self::LinkPagamento => 'bg-label-secondary',
        };
    }

    /**
     * A forma de pagamento permite escolher o número de parcelas?
     */
    public function permiteParcelas(): bool
    {
        return in_array($this, [self::CartaoCredito, self::LinkPagamento], true);
    }

    /**
     * Números de parcelas disponíveis (1 a 12).
     *
     * @return array<int, int>
     */
    public static function parcelasDisponiveis(): array
    {
        return range(1, self::MAX_PARCELAS);
    }

    /**
     * Opções para popular selects/menus.
     *
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $forma) => [$forma->value => $forma->label()])
            ->all();
    }

    /**
     * Formas que pedem o número de parcelas.
     *
     * @return array<int, string>
     */
    public static function comParcelas(): array
    {
        return collect(self::cases())
            ->filter(fn (self $forma) => $forma->permiteParcelas())
            ->map(fn (self $forma) => $forma->value)
            ->values()
            ->all();
    }
}
