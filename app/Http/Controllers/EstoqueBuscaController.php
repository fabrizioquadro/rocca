<?php

namespace App\Http\Controllers;

use App\Models\EntradaItem;
use Illuminate\Http\Request;

class EstoqueBuscaController extends Controller
{
    /**
     * Busca um código de barras e devolve os dados do lote (medicamento, lote,
     * vencimento) e o saldo disponível na clínica informada.
     *
     * É usado pelos formulários de baixa e de transferência.
     */
    public function buscarCodigoBarras(Request $request)
    {
        $codigo = trim((string) $request->input('codigo_barras'));
        $clinicaId = (int) $request->input('clinica_id');

        if ($codigo === '') {
            return response()->json(['ok' => false, 'mensagem' => 'Informe o código de barras.']);
        }

        if (! $clinicaId) {
            return response()->json(['ok' => false, 'mensagem' => 'Selecione a clínica primeiro.']);
        }

        $itens = EntradaItem::lotesComSaldo($codigo, $clinicaId);

        if ($itens->isEmpty()) {
            $existe = EntradaItem::where('codigo_barras', $codigo)->whereHas('entrada')->exists();

            return response()->json([
                'ok' => false,
                'mensagem' => $existe
                    ? 'O código de barras '.$codigo.' não tem saldo nesta clínica.'
                    : 'Código de barras '.$codigo.' não encontrado.',
            ]);
        }

        $primeiro = $itens->first();

        return response()->json([
            'ok' => true,
            'entrada_item_id' => $primeiro->id,
            'codigo_barras' => $primeiro->codigo_barras,
            'medicamento_id' => $primeiro->medicamento_id,
            'medicamento' => $primeiro->medicamento?->nome,
            'lote' => $primeiro->lote,
            'vencimento' => $primeiro->vencimento_formatado,
            'saldo' => (int) $itens->sum('saldo_clinica'),
        ]);
    }
}
