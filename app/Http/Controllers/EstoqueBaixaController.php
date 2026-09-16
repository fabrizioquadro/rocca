<?php

namespace App\Http\Controllers;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Baixa;
use App\Models\Clinica;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EstoqueBaixaController extends Controller
{
    /**
     * Lista as baixas lançadas.
     */
    public function index()
    {
        $baixas = Baixa::with(['clinica', 'user', 'itens.medicamento', 'itens.entradaItem'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        return view('estoque.baixas.index', compact('baixas'));
    }

    /**
     * Formulário de baixa (perda, avaria, quebra, vencimento e etc.).
     */
    public function create()
    {
        $clinicas = Clinica::orderBy('nome')->get();
        $itensIniciais = old('itens', []);

        return view('estoque.baixas.create', compact('clinicas', 'itensIniciais'));
    }

    /**
     * Lança a baixa: retira do estoque pelo código de barras.
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $baixa = DB::transaction(function () use ($dados) {
            $baixa = Baixa::create([
                'clinica_id' => $dados['clinica_id'],
                'user_id' => auth()->id(),
                'data' => $dados['data'] ?? now()->toDateString(),
            ]);

            $this->baixarItens($baixa, $dados['itens']);

            return $baixa;
        });

        return redirect()
            ->route('estoque.baixas.show', $baixa)
            ->with('success', 'Baixa lançada com sucesso.');
    }

    /**
     * Detalhes de uma baixa.
     */
    public function show(Baixa $baixa)
    {
        $baixa->load(['clinica', 'user', 'itens.medicamento', 'itens.entradaItem']);

        return view('estoque.baixas.show', compact('baixa'));
    }

    /**
     * Exclui a baixa, devolvendo as unidades ao estoque (estorno).
     */
    public function destroy(Baixa $baixa)
    {
        $baixa->load('itens');

        DB::transaction(function () use ($baixa) {
            foreach ($baixa->itens as $item) {
                EstoqueMovimentacao::create([
                    'medicamento_id' => $item->medicamento_id,
                    'entrada_item_id' => $item->entrada_item_id,
                    'clinica_id' => $baixa->clinica_id,
                    'tipo' => TipoMovimentacaoEstoque::Estorno,
                    'quantidade' => (int) $item->quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Estorno da exclusão da baixa #'.$baixa->id,
                ]);
            }

            $baixa->delete();
        });

        return redirect()
            ->route('estoque.baixas.index')
            ->with('success', 'Baixa excluída e estoque estornado.');
    }

    /**
     * Retira os itens do estoque (por código de barras, respeitando o vencimento).
     */
    private function baixarItens(Baixa $baixa, array $itens): void
    {
        foreach ($itens as $item) {
            $distribuicao = EntradaItem::distribuirPorCodigo(
                $item['codigo_barras'],
                (int) $baixa->clinica_id,
                (int) $item['quantidade']
            );

            foreach ($distribuicao as $entradaItemId => $quantidade) {
                $entradaItem = EntradaItem::find($entradaItemId);

                if (! $entradaItem) {
                    continue;
                }

                $baixa->itens()->create([
                    'entrada_item_id' => $entradaItemId,
                    'medicamento_id' => $entradaItem->medicamento_id,
                    'quantidade' => $quantidade,
                    'motivo' => $item['motivo'],
                ]);

                EstoqueMovimentacao::create([
                    'medicamento_id' => $entradaItem->medicamento_id,
                    'entrada_item_id' => $entradaItemId,
                    'clinica_id' => $baixa->clinica_id,
                    'tipo' => TipoMovimentacaoEstoque::Baixa,
                    'quantidade' => -1 * $quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Baixa #'.$baixa->id.' — '.$item['motivo'].' ('.$entradaItem->codigo_barras.')',
                ]);
            }
        }
    }

    /**
     * Valida os dados da baixa.
     */
    private function validar(Request $request): array
    {
        $itens = collect($request->input('itens', []))
            ->map(fn ($item) => [
                'codigo_barras' => filled($item['codigo_barras'] ?? null) ? trim($item['codigo_barras']) : null,
                'quantidade' => filled($item['quantidade'] ?? null) ? $item['quantidade'] : null,
                'motivo' => filled($item['motivo'] ?? null) ? trim($item['motivo']) : null,
            ])
            ->filter(fn ($item) => filled($item['codigo_barras']) || filled($item['quantidade']) || filled($item['motivo']))
            ->values()
            ->all();

        $request->merge(['itens' => $itens]);

        return $request->validate(
            [
                'clinica_id' => ['required', Rule::exists('clinicas', 'id')],
                'data' => ['nullable', 'date'],
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.codigo_barras' => ['required', 'string', 'max:100', $this->codigoComSaldo($request)],
                'itens.*.quantidade' => ['required', 'integer', 'min:1'],
                'itens.*.motivo' => ['required', 'string', 'max:1000'],
            ],
            [
                'clinica_id.required' => 'Selecione a clínica onde está o estoque.',
                'itens.required' => 'Adicione pelo menos um medicamento.',
                'itens.min' => 'Adicione pelo menos um medicamento.',
                'itens.*.codigo_barras.required' => 'Informe o código de barras em todas as linhas.',
                'itens.*.quantidade.required' => 'Informe a quantidade em todas as linhas.',
                'itens.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
                'itens.*.quantidade.min' => 'A quantidade deve ser pelo menos 1.',
                'itens.*.motivo.required' => 'Informe o motivo da baixa de cada medicamento.',
                'itens.*.motivo.max' => 'O motivo deve ter no máximo 1000 caracteres.',
            ]
        );
    }

    /**
     * Verifica o saldo do código de barras na clínica (bloqueia se insuficiente).
     */
    private function codigoComSaldo(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request) {
            if (blank($value)) {
                return;
            }

            $clinicaId = (int) $request->input('clinica_id');

            if (! $clinicaId) {
                return;
            }

            preg_match('/^itens\.(\d+)\.codigo_barras$/', $attribute, $partes);
            $quantidade = (int) $request->input('itens.'.($partes[1] ?? 0).'.quantidade');

            $saldo = EntradaItem::saldoPorCodigo($value, $clinicaId);

            if ($saldo <= 0) {
                $fail('O código de barras '.$value.' não tem saldo nesta clínica.');
                return;
            }

            if ($quantidade > $saldo) {
                $fail('Saldo insuficiente para o código de barras '.$value.' (disponível: '.$saldo.').');
            }
        };
    }
}
