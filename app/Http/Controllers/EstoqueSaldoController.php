<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\Medicamento;
use App\Models\VasilhameAberto;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EstoqueSaldoController extends Controller
{
    /**
     * Lista o saldo em estoque de cada medicamento.
     */
    public function index()
    {
        $medicamentos = Medicamento::query()
            ->with('grupo')
            ->withSum('movimentacoes as saldo', 'quantidade')
            // Medicamento por mg: o que está aberto tem saldo próprio
            ->withSum(['vasilhamesAbertos as mg_abertos' => fn ($query) => $query->emUso()], 'mg_restantes')
            ->orderBy('nome')
            ->get();

        return view('estoque.saldo.index', compact('medicamentos'));
    }

    /**
     * Detalha o estoque de um medicamento, separado por clínica,
     * código de barras e lote (e os vasilhames abertos, quando for por mg).
     */
    public function show(Medicamento $medicamento)
    {
        $linhas = $this->saldosPorLote($medicamento);

        $total = (int) $linhas->sum('quantidade');

        $vasilhames = VasilhameAberto::with(['entradaItem', 'clinica', 'abertoPor'])
            ->where('medicamento_id', $medicamento->id)
            ->orderByDesc('aberto_em')
            ->get();

        // Primeiro os vasilhames ainda em uso, depois os esgotados
        $vasilhames = $vasilhames->filter(fn (VasilhameAberto $v) => $v->esta_em_uso)
            ->concat($vasilhames->reject(fn (VasilhameAberto $v) => $v->esta_em_uso))
            ->values();

        $mgAbertos = (float) $vasilhames->filter(fn (VasilhameAberto $v) => $v->esta_em_uso)
            ->sum('mg_restantes');

        return view('estoque.saldo.show', compact('medicamento', 'linhas', 'total', 'vasilhames', 'mgAbertos'));
    }

    /**
     * Inventário de um código de barras (lote) em uma clínica: entrada,
     * saídas, transferências, aplicações e baixas — mesmo que o saldo esteja
     * zerado.
     */
    public function inventario(Request $request, EntradaItem $entradaItem)
    {
        $clinicaId = (int) $request->input('clinica_id');

        if (! $clinicaId) {
            return redirect()
                ->route('estoque.saldo.show', $entradaItem->medicamento_id)
                ->with('error', 'Escolha a clínica para ver o inventário do código de barras.');
        }

        $entradaItem->load(['medicamento', 'entrada.fornecedor']);

        // Tudo que entrou e saiu do estoque fechado deste lote
        $movimentacoes = EstoqueMovimentacao::with(['user', 'clinica'])
            ->where('entrada_item_id', $entradaItem->id)
            ->where('clinica_id', $clinicaId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $saldo = (int) $movimentacoes->sum('quantidade');

        // Medicamento por mg: os vasilhames abertos e o que saiu de cada um
        $vasilhames = $entradaItem->medicamento?->eh_miligrama
            ? VasilhameAberto::with([
                'abertoPor',
                'aplicacoes.user',
                'aplicacoes.item.semana.prescricao.paciente',
                'baixas.baixa.user',
            ])
                ->where('entrada_item_id', $entradaItem->id)
                ->where('clinica_id', $clinicaId)
                ->orderBy('aberto_em')
                ->get()
            : collect();

        $clinica = Clinica::find($clinicaId);

        return view('estoque.saldo.inventario', compact(
            'entradaItem',
            'movimentacoes',
            'saldo',
            'vasilhames',
            'clinica'
        ));
    }

    /**
     * Linhas de saldo: clínica + código de barras + lote + vencimento + quantidade.
     */
    private function saldosPorLote(Medicamento $medicamento): Collection
    {
        $saldos = EstoqueMovimentacao::query()
            ->where('medicamento_id', $medicamento->id)
            ->whereNotNull('entrada_item_id')
            ->whereNotNull('clinica_id')
            ->selectRaw('entrada_item_id, clinica_id, SUM(quantidade) as saldo')
            ->groupBy('entrada_item_id', 'clinica_id')
            ->havingRaw('SUM(quantidade) > 0')
            ->get();

        if ($saldos->isEmpty()) {
            return collect();
        }

        $itens = EntradaItem::query()
            ->whereIn('id', $saldos->pluck('entrada_item_id'))
            ->get()
            ->keyBy('id');

        $clinicas = Clinica::query()
            ->whereIn('id', $saldos->pluck('clinica_id'))
            ->get()
            ->keyBy('id');

        return $saldos
            ->map(function ($saldo) use ($itens, $clinicas) {
                $item = $itens->get($saldo->entrada_item_id);

                return [
                    'entrada_item_id' => $item?->id,
                    'clinica_id' => (int) $saldo->clinica_id,
                    'clinica' => $clinicas->get($saldo->clinica_id)?->nome ?? '—',
                    'codigo_barras' => $item?->codigo_barras ?? '—',
                    'lote' => $item?->lote ?? '—',
                    'vencimento' => $item?->vencimento,
                    'vencimento_formatado' => $item?->vencimento_formatado ?? '—',
                    'quantidade' => (int) $saldo->saldo,
                ];
            })
            ->sortBy(fn (array $linha) => [
                $linha['clinica'],
                $linha['vencimento']?->format('Y-m-d') ?? '9999-12-31',
                $linha['codigo_barras'],
            ])
            ->values();
    }
}
