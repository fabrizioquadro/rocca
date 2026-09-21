<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\TipoLogPrescricao;
use App\Models\FinanceiroPagamento;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Services\FinanceiroPagamentoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pagamentos do financeiro da prescrição. O valor recebido é alocado da
 * primeira para a última parcela.
 */
class FinanceiroPagamentoController extends Controller
{
    public function __construct(private FinanceiroPagamentoService $pagamentos)
    {
    }

    /**
     * Lança um pagamento e recalcula as parcelas.
     */
    public function store(Request $request, Prescricao $prescricao)
    {
        $financeiro = $prescricao->financeiro;

        if (! $financeiro) {
            return redirect()
                ->route('prescricoes.show', $prescricao)
                ->with('error', 'Esta prescrição não tem parcelas para receber pagamento.');
        }

        $dados = $request->validate([
            'valor' => ['required'],
            'forma_pagamento' => ['required', Rule::enum(FormaPagamento::class)],
            'parcelas' => ['nullable', 'integer', 'min:1', 'max:'.FormaPagamento::MAX_PARCELAS],
            'data_pagamento' => ['required', 'date'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], [
            'valor.required' => 'Informe o valor do pagamento.',
            'forma_pagamento.required' => 'Informe a forma de pagamento.',
            'forma_pagamento.enum' => 'Informe uma forma de pagamento válida.',
            'parcelas.integer' => 'O número de parcelas deve ser de 1 a '.FormaPagamento::MAX_PARCELAS.'.',
            'parcelas.min' => 'O número de parcelas deve ser de 1 a '.FormaPagamento::MAX_PARCELAS.'.',
            'parcelas.max' => 'O número de parcelas deve ser de 1 a '.FormaPagamento::MAX_PARCELAS.'.',
            'data_pagamento.required' => 'Informe a data do pagamento.',
        ]);

        $pagamento = $this->pagamentos->registrar($financeiro, $dados);

        PrescricaoLog::registrar($prescricao, TipoLogPrescricao::PagamentoRegistrado, 'Pagamento registrado: '
            .$pagamento->valor_formatado.'.', [
            'detalhes' => array_filter([
                'Valor' => $pagamento->valor_formatado,
                'Forma de pagamento' => $pagamento->forma_label,
                'Parcelas' => (string) $pagamento->parcelas,
                'Data do pagamento' => $pagamento->data_formatada,
                'Observação' => $pagamento->observacao,
                'Total recebido' => $financeiro->refresh()->valor_recebido_formatado,
            ]),
        ]);

        return redirect()
            ->route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro'])
            ->with('success', 'Pagamento registrado e parcelas atualizadas.');
    }

    /**
     * Remove um pagamento e recalcula as parcelas.
     */
    public function destroy(Prescricao $prescricao, FinanceiroPagamento $pagamento)
    {
        abort_if($pagamento->financeiro?->prescricao_id !== $prescricao->id, 404);

        PrescricaoLog::registrar($prescricao, TipoLogPrescricao::PagamentoRemovido, 'Pagamento removido: '
            .$pagamento->valor_formatado.'.', [
            'detalhes' => array_filter([
                'Valor' => $pagamento->valor_formatado,
                'Forma de pagamento' => $pagamento->forma_label,
                'Data do pagamento' => $pagamento->data_formatada,
                'Observação' => $pagamento->observacao,
            ]),
        ]);

        $this->pagamentos->excluir($pagamento);

        return redirect()
            ->route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro'])
            ->with('success', 'Pagamento removido e parcelas recalculadas.');
    }
}
