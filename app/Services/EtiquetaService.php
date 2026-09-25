<?php

namespace App\Services;

use App\Models\Entrada;
use App\Models\EntradaItem;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Monta as etiquetas (código de barras + número) das entradas de estoque.
 *
 * Cada unidade recebida vira uma etiqueta: um item com quantidade 100 gera 100
 * etiquetas iguais. O SVG do código é gerado uma única vez por código e
 * reaproveitado nas repetições — assim uma entrada grande gera pouco HTML.
 */
class EtiquetaService
{
    /**
     * Limite de segurança de etiquetas por impressão (evita travar o navegador
     * numa bobina inteira). Em produção normal fica bem abaixo disso.
     */
    public const LIMITE = 3000;

    private BarcodeGenerator $gerador;

    public function __construct()
    {
        $this->gerador = new BarcodeGeneratorSVG();
    }

    /**
     * Etiquetas de todos os itens da entrada (uma por unidade recebida).
     *
     * @return array<int, array{codigo: string, id: string, viewBox: string, conteudo: string, medicamento: ?string, lote: ?string}>
     */
    public function daEntrada(Entrada $entrada): array
    {
        return $this->montar($entrada->itens, null);
    }

    /**
     * Etiquetas de um único item (permite reimprimir só uma parte).
     *
     * @return array<int, array{codigo: string, id: string, viewBox: string, conteudo: string, medicamento: ?string, lote: ?string}>
     */
    public function doItem(EntradaItem $item, ?int $quantidade = null): array
    {
        return $this->montar(collect([$item]), $quantidade);
    }

    /**
     * Código de barras (CODE 128) pronto para virar um símbolo SVG.
     *
     * O código do estoque é numérico (ex.: 1600021), mas o CODE 128 aceita
     * qualquer texto do catálogo.
     *
     * @return array{id: string, viewBox: string, conteudo: string}
     */
    public function codigoDeBarras(string $codigo): array
    {
        $codigo = trim($codigo);
        $vazio = ['id' => '', 'viewBox' => '0 0 200 60', 'conteudo' => ''];

        if ($codigo === '') {
            return $vazio;
        }

        try {
            $svg = $this->gerador->getBarcode($codigo, BarcodeGenerator::TYPE_CODE_128, 2, 60);
        } catch (\Throwable $e) {
            // Código fora do padrão: a etiqueta sai só com o número.
            return $vazio;
        }

        return [
            'id' => 'cb-'.preg_replace('/[^A-Za-z0-9]/', '-', $codigo),
            'viewBox' => preg_match('/viewBox="([^"]+)"/', $svg, $achado) ? $achado[1] : $vazio['viewBox'],
            'conteudo' => preg_match('#<svg[^>]*>(.*)</svg>#s', $svg, $achado) ? trim($achado[1]) : '',
        ];
    }

    /**
     * @param  iterable<EntradaItem>  $itens
     * @return array<int, array{codigo: string, id: string, viewBox: string, conteudo: string, medicamento: ?string, lote: ?string}>
     */
    private function montar(iterable $itens, ?int $quantidade): array
    {
        $etiquetas = [];

        foreach ($itens as $item) {
            if (trim((string) $item->codigo_barras) === '') {
                continue;
            }

            $barras = $this->codigoDeBarras((string) $item->codigo_barras);
            $quantas = $quantidade ?? (int) $item->quantidade;

            for ($i = 0; $i < $quantas; $i++) {
                $etiquetas[] = [
                    'codigo' => trim((string) $item->codigo_barras),
                    'id' => $barras['id'],
                    'viewBox' => $barras['viewBox'],
                    'conteudo' => $barras['conteudo'],
                    'medicamento' => $item->medicamento?->nome,
                    'lote' => $item->lote,
                ];
            }
        }

        return $etiquetas;
    }
}
