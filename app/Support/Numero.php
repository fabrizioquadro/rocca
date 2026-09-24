<?php

namespace App\Support;

/**
 * Conversão de números digitados na tela (pt-BR) para float.
 */
class Numero
{
    /**
     * Aceita "1.234,56", "10,5", "10.5" e "1000".
     * Depois de limpar, "10,5" vira 10.5.
     */
    public static function paraFloat($valor): float
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return 0.0;
        }

        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }

        return (float) $texto;
    }

    /**
     * Formata um número para exibição, sem zeros desnecessários
     * (ex.: 85,75 / 4,25 / 10).
     */
    public static function formatar($valor, int $casas = 3): string
    {
        return rtrim(rtrim(number_format((float) $valor, $casas, ',', '.'), '0'), ',');
    }

    /**
     * Converte um texto exibido na tela em valor de planilha.
     *
     * Entende "2 mg", "R$ 1.234,56" e "- R$ 12,00". Preserva como texto
     * códigos com zero à esquerda (ex.: 0100004) e datas (24/09/2026).
     * Devolve null para células vazias (—), para a planilha ficar vazia.
     */
    public static function paraCelula($valor): float|int|string|null
    {
        $texto = trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');

        if ($texto === '' || $texto === '—' || $texto === '-') {
            return null;
        }

        $numero = $texto;
        $negativo = (bool) preg_match('/^-\s*/', $numero);
        $numero = trim(preg_replace('/^-\s*/', '', $numero) ?? '');
        $numero = trim(preg_replace('/^R\$\s*/i', '', $numero) ?? '');
        $numero = trim(preg_replace('/\s*mg$/i', '', $numero) ?? '');

        // Ainda tem letras: é texto (ex.: "2 mg + 2,25 mg", "Cartão 3x").
        if (preg_match('/[A-Za-zÀ-ÿ]/u', $numero)) {
            return $texto;
        }

        if (! preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d+)?$/', $numero)) {
            return $texto;
        }

        // Código com zero à esquerda continua texto (0,5 é quantidade).
        if (str_starts_with($numero, '0') && ! preg_match('/^0,\d+$/', $numero)) {
            return $texto;
        }

        $flutuante = (float) str_replace(['.', ','], ['', '.'], $numero);

        return $negativo ? -$flutuante : $flutuante;
    }

    /**
     * O texto é um número/valor (usa o mesmo critério da planilha)?
     */
    public static function pareceNumero($valor): bool
    {
        return is_float(self::paraCelula($valor));
    }
}
