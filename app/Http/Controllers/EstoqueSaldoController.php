<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\Medicamento;
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
            ->orderBy('nome')
            ->get();

        return view('estoque.saldo.index', compact('medicamentos'));
    }

    /**
     * Detalha o estoque de um medicamento, separado por clínica,
     * código de barras e lote.
     */
    public function show(Medicamento $medicamento)
    {
        $linhas = $this->saldosPorLote($medicamento);

        $total = (int) $linhas->sum('quantidade');

        return view('estoque.saldo.show', compact('medicamento', 'linhas', 'total'));
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
