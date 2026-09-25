<?php

namespace App\Http\Controllers;

use App\Models\Entrada;
use App\Models\EntradaItem;
use App\Services\EtiquetaService;
use Illuminate\Http\Request;

/**
 * Impressão das etiquetas (código de barras) das entradas de estoque.
 *
 * A bobina tem 100mm de largura com 3 etiquetas de 30x15mm por linha: a tela
 * de impressão é montada nessa medida (ver resources/views/estoque/entradas/etiquetas.blade.php).
 */
class EstoqueEtiquetaController extends Controller
{
    public function __construct(private EtiquetaService $etiquetas)
    {
    }

    /**
     * Etiquetas de todos os medicamentos da entrada (uma por unidade recebida).
     */
    public function entrada(Entrada $entrada)
    {
        $entrada->load(['itens.medicamento', 'clinica', 'fornecedor']);

        $etiquetas = $this->etiquetas->daEntrada($entrada);

        return view('estoque.entradas.etiquetas', [
            'entrada' => $entrada,
            'item' => null,
            'etiquetas' => array_slice($etiquetas, 0, EtiquetaService::LIMITE),
            'total' => count($etiquetas),
            'quantidade' => null,
        ]);
    }

    /**
     * Etiquetas de um único medicamento da entrada.
     *
     * `?quantidade=N` reimprime apenas N etiquetas (ex.: etiqueta danificada);
     * sem o parâmetro sai a quantidade cheia do item.
     */
    public function item(Request $request, Entrada $entrada, EntradaItem $entrada_item)
    {
        abort_unless((int) $entrada_item->entrada_id === (int) $entrada->id, 404);

        $entrada_item->load('medicamento');

        $quantidade = (int) $request->input('quantidade', $entrada_item->quantidade);
        $quantidade = max(1, min(EtiquetaService::LIMITE, $quantidade));

        $etiquetas = $this->etiquetas->doItem($entrada_item, $quantidade);

        return view('estoque.entradas.etiquetas', [
            'entrada' => $entrada,
            'item' => $entrada_item,
            'etiquetas' => $etiquetas,
            'quantidade' => $quantidade,
        ]);
    }
}
