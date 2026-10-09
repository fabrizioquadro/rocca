<?php

namespace App\Services;

use App\Enums\FormaPagamento;
use App\Enums\StatusParcela;
use App\Models\Financeiro;
use App\Models\FinanceiroPagamento;
use App\Models\FinanceiroParcela;
use App\Support\Numero;
use Illuminate\Validation\ValidationException;

/**
 * Pagamentos do financeiro. Os pagamentos são alocados sempre da primeira
 * parcela para a última (cascata): a parcela 1 recebe até o seu valor, o que
 * sobrar vai para a 2ª e assim por diante.
 *
 * A alocação é derivada do total recebido, então qualquer mudança no valor das
 * parcelas (medicamento incluído, desconto alterado, semana excluída) é
 * resolvida chamando reprocessar() — nada fica "preso" numa parcela.
 */
class FinanceiroPagamentoService
{
    /**
     * Lança um pagamento e redistribui o recebido nas parcelas.
     *
     * @param  array{valor?: mixed, forma_pagamento?: mixed, parcelas?: mixed, data_pagamento?: mixed, observacao?: ?string, identificador?: ?string}  $dados
     */
    public function registrar(Financeiro $financeiro, array $dados): FinanceiroPagamento
    {
        $dados = $this->normalizar($dados);

        $pagamento = $financeiro->pagamentos()->create([
            'valor' => $dados['valor'],
            'identificador' => $dados['identificador'],
            'forma_pagamento' => $dados['forma_pagamento'],
            'parcelas' => $dados['parcelas'],
            'data_pagamento' => $dados['data_pagamento'],
            'observacao' => $dados['observacao'],
            'user_id' => auth()->id(),
        ]);

        $this->reprocessar($financeiro);

        return $pagamento;
    }

    /**
     * Corrige um pagamento já lançado (valor, forma, parcelas, data, ID e
     * observação) e redistribui o recebido nas parcelas. Quem registrou
     * originalmente é mantido.
     *
     * @param  array{valor?: mixed, forma_pagamento?: mixed, parcelas?: mixed, data_pagamento?: mixed, observacao?: ?string, identificador?: ?string}  $dados
     */
    public function atualizar(FinanceiroPagamento $pagamento, array $dados): FinanceiroPagamento
    {
        $dados = $this->normalizar($dados, $pagamento);

        $pagamento->update([
            'valor' => $dados['valor'],
            'identificador' => $dados['identificador'],
            'forma_pagamento' => $dados['forma_pagamento'],
            'parcelas' => $dados['parcelas'],
            'data_pagamento' => $dados['data_pagamento'],
            'observacao' => $dados['observacao'],
        ]);

        if ($financeiro = $pagamento->financeiro) {
            $this->reprocessar($financeiro);
        }

        return $pagamento;
    }

    /**
     * Valida e normaliza os dados do pagamento. O que não vier no formulário
     * mantém o valor atual (usado na edição).
     *
     * @param  array<string, mixed>  $dados
     * @return array{valor: float, identificador: ?string, forma_pagamento: string, parcelas: int, data_pagamento: string, observacao: ?string}
     */
    private function normalizar(array $dados, ?FinanceiroPagamento $pagamento = null): array
    {
        $valor = round(Numero::paraFloat($dados['valor'] ?? null), 2);

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'valor' => 'Informe um valor de pagamento maior que zero.',
            ]);
        }

        $forma = FormaPagamento::tryFrom((string) ($dados['forma_pagamento'] ?? '')) ?? $pagamento?->forma_pagamento;

        if (! $forma) {
            throw ValidationException::withMessages([
                'forma_pagamento' => 'Informe a forma de pagamento.',
            ]);
        }

        $parcelas = (int) ($dados['parcelas'] ?? 1);

        if ($forma->permiteParcelas()) {
            if ($parcelas < 1 || $parcelas > FormaPagamento::MAX_PARCELAS) {
                throw ValidationException::withMessages([
                    'parcelas' => 'Informe o número de parcelas de 1 a '.FormaPagamento::MAX_PARCELAS.'.',
                ]);
            }
        } else {
            // Dinheiro, débito e Pix não têm parcelamento
            $parcelas = 1;
        }

        return [
            'valor' => $valor,
            'identificador' => filled($dados['identificador'] ?? null)
                ? trim((string) $dados['identificador'])
                : $pagamento?->identificador,
            'forma_pagamento' => $forma->value,
            'parcelas' => $parcelas,
            'data_pagamento' => $dados['data_pagamento'] ?? $pagamento?->data_pagamento?->toDateString() ?? now()->toDateString(),
            'observacao' => filled($dados['observacao'] ?? null) ? $dados['observacao'] : null,
        ];
    }

    /**
     * Remove um pagamento e redistribui o que sobrou.
     */
    public function excluir(FinanceiroPagamento $pagamento): void
    {
        $financeiro = $pagamento->financeiro;

        $pagamento->delete();

        if ($financeiro) {
            $this->reprocessar($financeiro);
        }
    }

    /**
     * Redistribui o total recebido entre as parcelas, da primeira para a
     * última. O que sobrar depois da última parcela fica como crédito
     * (Financeiro::valor_nao_alocado).
     */
    public function reprocessar(Financeiro $financeiro): void
    {
        $disponivel = $financeiro->valor_recebido;

        $parcelas = FinanceiroParcela::where('financeiro_id', $financeiro->id)
            ->orderBy('numero')
            ->get();

        foreach ($parcelas as $parcela) {
            $valor = round((float) $parcela->valor, 2);

            $pago = round(min(max($disponivel, 0), max($valor, 0)), 2);
            $disponivel = round($disponivel - $pago, 2);

            if ($pago <= 0) {
                $status = StatusParcela::Aberta;
            } elseif ($pago >= $valor) {
                $status = StatusParcela::Paga;
            } else {
                $status = StatusParcela::Parcial;
            }

            if ((float) $parcela->valor_pago !== $pago || $parcela->status !== $status) {
                $parcela->valor_pago = $pago;
                $parcela->status = $status;
                $parcela->save();
            }
        }
    }

    /**
     * Diz quais recebimentos (pagamentos) caíram em cada parcela, seguindo a
     * MESMA cascata do reprocessar(): pagamentos do mais antigo para o mais novo,
     * preenchendo da 1ª parcela para a última. É derivado (nada fica gravado) e
     * serve para exibir o ID/NSU de cada recebimento ao lado da parcela.
     *
     * @param  iterable<FinanceiroPagamento>  $pagamentos
     * @param  iterable<FinanceiroParcela>  $parcelas
     * @return array<int, list<FinanceiroPagamento>>  [parcela_id => pagamentos que a preencheram]
     */
    public static function recebimentosPorParcela(iterable $pagamentos, iterable $parcelas): array
    {
        $fila = collect($parcelas)->sortBy('numero')->values();

        $restante = $fila->mapWithKeys(fn (FinanceiroParcela $parcela) => [
            $parcela->id => round(max((float) $parcela->valor, 0), 2),
        ])->all();

        $recebimentos = array_fill_keys(array_keys($restante), []);

        $ordenados = collect($pagamentos)->sortBy([
            ['data_pagamento', 'asc'],
            ['id', 'asc'],
        ]);

        foreach ($ordenados as $pagamento) {
            $disponivel = round((float) $pagamento->valor, 2);

            foreach (array_keys($restante) as $parcelaId) {
                if ($disponivel <= 0) {
                    break;
                }

                $pago = round(min($restante[$parcelaId], $disponivel), 2);

                if ($pago <= 0) {
                    continue;
                }

                $restante[$parcelaId] = round($restante[$parcelaId] - $pago, 2);
                $disponivel = round($disponivel - $pago, 2);
                $recebimentos[$parcelaId][] = $pagamento;
            }
        }

        return $recebimentos;
    }
}
