<?php

namespace App\Http\Controllers;

use App\Models\Prescricao;
use App\Services\PrescricaoSemanaService;
use App\Support\Numero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ajustes do financeiro da prescrição: desconto, adicional e observação.
 * Na edição o desconto é sempre em valor (R$) — o percentual do cadastro não
 * é recalculado.
 */
class FinanceiroController extends Controller
{
    public function __construct(private PrescricaoSemanaService $semanas)
    {
    }

    /**
     * Grava o desconto/adicional/observação e recalcula as parcelas.
     */
    public function update(Request $request, Prescricao $prescricao)
    {
        $financeiro = $prescricao->financeiro;

        if (! $financeiro) {
            return redirect()
                ->route('prescricoes.show', $prescricao)
                ->with('error', 'Esta prescrição não tem financeiro para ajustar.');
        }

        $dados = $request->validate([
            'desconto_valor' => ['nullable'],
            'adicional_valor' => ['nullable'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($prescricao, $financeiro, $dados) {
            $financeiro->update(['observacao' => $dados['observacao'] ?? null]);

            // O desconto da edição entra sempre como valor fixo (R$)
            $this->semanas->sincronizarFinanceiro($prescricao->refresh(), [
                'desconto_tipo' => 'valor',
                'desconto_valor' => Numero::paraFloat($dados['desconto_valor'] ?? null),
                'adicional_valor' => Numero::paraFloat($dados['adicional_valor'] ?? null),
            ]);
        });

        return redirect()
            ->route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro'])
            ->with('success', 'Financeiro atualizado e parcelas recalculadas.');
    }
}
