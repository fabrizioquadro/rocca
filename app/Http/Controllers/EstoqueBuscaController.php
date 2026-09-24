<?php

namespace App\Http\Controllers;

use App\Models\EntradaItem;
use App\Models\VasilhameAberto;
use App\Services\EstoqueVasilhameService;
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

            // Medicamento por mg já aberto: o saldo dele não está mais aqui
            $aberto = VasilhameAberto::emUso()
                ->where('clinica_id', $clinicaId)
                ->whereHas('entradaItem', fn ($query) => $query->where('codigo_barras', $codigo))
                ->exists();

            return response()->json([
                'ok' => false,
                'mensagem' => $aberto
                    ? 'O vasilhame '.$codigo.' está aberto (em uso) — o saldo dele é controlado em mg.'
                    : ($existe
                        ? 'O código de barras '.$codigo.' não tem saldo nesta clínica.'
                        : 'Código de barras '.$codigo.' não encontrado.'),
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

    /**
     * Situação de um vasilhame (medicamento do tipo miligrama) para a tela de
     * aplicação: aberto (com o saldo em mg), fechado, esgotado, vencido e etc.
     */
    public function buscarVasilhame(Request $request, EstoqueVasilhameService $vasilhames)
    {
        $codigo = trim((string) $request->input('codigo_barras'));
        $clinicaId = (int) $request->input('clinica_id');

        if ($codigo === '') {
            return response()->json([
                'ok' => false,
                'estado' => 'vazio',
                'mensagem' => 'Informe o código de barras.',
            ]);
        }

        if (! $clinicaId) {
            return response()->json([
                'ok' => false,
                'estado' => 'sem_clinica',
                'mensagem' => 'Selecione a clínica primeiro.',
            ]);
        }

        // A aplicação pode aceitar mais de um medicamento (mesmo produto/grupo)
        $medicamentoIds = collect(explode(',', (string) $request->input('medicamento_ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();

        if ($medicamentoIds === [] && $request->filled('medicamento_id')) {
            $medicamentoIds = [(int) $request->input('medicamento_id')];
        }

        $situacao = $vasilhames->situacaoDoCodigo($codigo, $clinicaId, $medicamentoIds);

        return response()->json(array_merge(
            ['ok' => $situacao['estado'] === 'aberto'],
            $situacao
        ));
    }
}
