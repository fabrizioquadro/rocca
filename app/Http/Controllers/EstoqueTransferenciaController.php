<?php

namespace App\Http\Controllers;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Clinica;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\Transferencia;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EstoqueTransferenciaController extends Controller
{
    /**
     * Lista as transferências entre clínicas.
     */
    public function index()
    {
        $transferencias = Transferencia::with([
            'clinica',
            'clinicaDestino',
            'user',
            'itens.medicamento',
            'itens.entradaItem',
        ])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        return view('estoque.transferencias.index', compact('transferencias'));
    }

    /**
     * Formulário de transferência entre clínicas.
     */
    public function create()
    {
        $clinicas = Clinica::orderBy('nome')->get();
        $itensIniciais = old('itens', []);

        return view('estoque.transferencias.create', compact('clinicas', 'itensIniciais'));
    }

    /**
     * Lança a transferência: sai do estoque da origem e entra no destino.
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $transferencia = DB::transaction(function () use ($dados) {
            $transferencia = Transferencia::create([
                'clinica_id' => $dados['clinica_id'],
                'clinica_destino_id' => $dados['clinica_destino_id'],
                'user_id' => auth()->id(),
                'observacao' => $dados['observacao'] ?? null,
                'data' => $dados['data'] ?? now()->toDateString(),
            ]);

            $this->transferirItens($transferencia, $dados['itens']);

            return $transferencia;
        });

        return redirect()
            ->route('estoque.transferencias.show', $transferencia)
            ->with('success', 'Transferência lançada com sucesso.');
    }

    /**
     * Detalhes de uma transferência.
     */
    public function show(Transferencia $transferencia)
    {
        $transferencia->load(['clinica', 'clinicaDestino', 'user', 'itens.medicamento', 'itens.entradaItem']);

        return view('estoque.transferencias.show', compact('transferencia'));
    }

    /**
     * Exclui a transferência, estornando o estoque (origem e destino).
     */
    public function destroy(Transferencia $transferencia)
    {
        $transferencia->load(['itens.entradaItem']);

        // Só pode estornar se o destino ainda tiver as unidades
        $faltando = [];

        foreach ($transferencia->itens as $item) {
            $saldoDestino = $item->entradaItem?->saldoNaClinica((int) $transferencia->clinica_destino_id) ?? 0;

            if ($saldoDestino < $item->quantidade) {
                $faltando[] = ($item->entradaItem?->codigo_barras ?? '#'.$item->entrada_item_id)
                    .' (disponível no destino: '.$saldoDestino.', necessário: '.$item->quantidade.')';
            }
        }

        if ($faltando) {
            return redirect()
                ->route('estoque.transferencias.index')
                ->with('error', 'Não é possível excluir: o destino já movimentou parte do estoque — '.implode(' | ', $faltando).'.');
        }

        DB::transaction(function () use ($transferencia) {
            foreach ($transferencia->itens as $item) {
                // Devolve para a origem
                EstoqueMovimentacao::create([
                    'medicamento_id' => $item->medicamento_id,
                    'entrada_item_id' => $item->entrada_item_id,
                    'clinica_id' => $transferencia->clinica_id,
                    'tipo' => TipoMovimentacaoEstoque::Estorno,
                    'quantidade' => (int) $item->quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Estorno da exclusão da transferência #'.$transferencia->id,
                ]);

                // Retira do destino
                EstoqueMovimentacao::create([
                    'medicamento_id' => $item->medicamento_id,
                    'entrada_item_id' => $item->entrada_item_id,
                    'clinica_id' => $transferencia->clinica_destino_id,
                    'tipo' => TipoMovimentacaoEstoque::Estorno,
                    'quantidade' => -1 * (int) $item->quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Estorno da exclusão da transferência #'.$transferencia->id,
                ]);
            }

            $transferencia->delete();
        });

        return redirect()
            ->route('estoque.transferencias.index')
            ->with('success', 'Transferência excluída e estoque estornado (origem e destino).');
    }

    /**
     * Movimenta os itens: saída na origem e entrada no destino.
     */
    private function transferirItens(Transferencia $transferencia, array $itens): void
    {
        foreach ($itens as $item) {
            $distribuicao = EntradaItem::distribuirPorCodigo(
                $item['codigo_barras'],
                (int) $transferencia->clinica_id,
                (int) $item['quantidade']
            );

            foreach ($distribuicao as $entradaItemId => $quantidade) {
                $entradaItem = EntradaItem::find($entradaItemId);

                if (! $entradaItem) {
                    continue;
                }

                $transferencia->itens()->create([
                    'entrada_item_id' => $entradaItemId,
                    'medicamento_id' => $entradaItem->medicamento_id,
                    'quantidade' => $quantidade,
                ]);

                // Saída da origem
                EstoqueMovimentacao::create([
                    'medicamento_id' => $entradaItem->medicamento_id,
                    'entrada_item_id' => $entradaItemId,
                    'clinica_id' => $transferencia->clinica_id,
                    'tipo' => TipoMovimentacaoEstoque::Transferencia,
                    'quantidade' => -1 * $quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Transferência #'.$transferencia->id.' para '.($transferencia->clinicaDestino?->nome ?? 'clínica destino'),
                ]);

                // Entrada no destino
                EstoqueMovimentacao::create([
                    'medicamento_id' => $entradaItem->medicamento_id,
                    'entrada_item_id' => $entradaItemId,
                    'clinica_id' => $transferencia->clinica_destino_id,
                    'tipo' => TipoMovimentacaoEstoque::Transferencia,
                    'quantidade' => $quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Transferência #'.$transferencia->id.' de '.($transferencia->clinica?->nome ?? 'clínica origem'),
                ]);
            }
        }
    }

    /**
     * Valida os dados da transferência.
     */
    private function validar(Request $request): array
    {
        $itens = collect($request->input('itens', []))
            ->map(fn ($item) => [
                'codigo_barras' => filled($item['codigo_barras'] ?? null) ? trim($item['codigo_barras']) : null,
                'quantidade' => filled($item['quantidade'] ?? null) ? $item['quantidade'] : null,
            ])
            ->filter(fn ($item) => filled($item['codigo_barras']) || filled($item['quantidade']))
            ->values()
            ->all();

        $request->merge(['itens' => $itens]);

        return $request->validate(
            [
                'clinica_id' => ['required', Rule::exists('clinicas', 'id')],
                'clinica_destino_id' => ['required', 'different:clinica_id', Rule::exists('clinicas', 'id')],
                'observacao' => ['nullable', 'string', 'max:1000'],
                'data' => ['nullable', 'date'],
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.codigo_barras' => ['required', 'string', 'max:100', $this->codigoComSaldo($request)],
                'itens.*.quantidade' => ['required', 'integer', 'min:1'],
            ],
            [
                'clinica_id.required' => 'Selecione a clínica de origem.',
                'clinica_destino_id.required' => 'Selecione a clínica de destino.',
                'clinica_destino_id.different' => 'A clínica de destino deve ser diferente da origem.',
                'itens.required' => 'Adicione pelo menos um medicamento.',
                'itens.min' => 'Adicione pelo menos um medicamento.',
                'itens.*.codigo_barras.required' => 'Informe o código de barras em todas as linhas.',
                'itens.*.quantidade.required' => 'Informe a quantidade em todas as linhas.',
                'itens.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
                'itens.*.quantidade.min' => 'A quantidade deve ser pelo menos 1.',
            ]
        );
    }

    /**
     * Verifica o saldo do código de barras na clínica de origem.
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
                $fail('O código de barras '.$value.' não tem saldo na clínica de origem.');
                return;
            }

            if ($quantidade > $saldo) {
                $fail('Saldo insuficiente para o código de barras '.$value.' (disponível: '.$saldo.').');
            }
        };
    }
}
