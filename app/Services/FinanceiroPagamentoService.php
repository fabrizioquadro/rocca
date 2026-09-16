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
     * @param  array{valor?: mixed, forma_pagamento?: mixed, parcelas?: mixed, data_pagamento?: mixed, observacao?: ?string}  $dados
     */
    public function registrar(Financeiro $financeiro, array $dados): FinanceiroPagamento
    {
        $valor = round(Numero::paraFloat($dados['valor'] ?? null), 2);

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'valor' => 'Informe um valor de pagamento maior que zero.',
            ]);
        }

        $forma = FormaPagamento::tryFrom((string) ($dados['forma_pagamento'] ?? ''));

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

        $pagamento = $financeiro->pagamentos()->create([
            'valor' => $valor,
            'forma_pagamento' => $forma->value,
            'parcelas' => $parcelas,
            'data_pagamento' => $dados['data_pagamento'] ?? now()->toDateString(),
            'observacao' => $dados['observacao'] ?? null,
            'user_id' => auth()->id(),
        ]);

        $this->reprocessar($financeiro);

        return $pagamento;
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
}
