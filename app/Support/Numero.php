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
}
