<?php

namespace App\Http\Controllers;

use App\Enums\TipoLogPrescricao;
use App\Models\Financeiro;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
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
            $antes = $this->resumoFinanceiro($financeiro);

            $financeiro->update(['observacao' => $dados['observacao'] ?? null]);

            // O desconto da edição entra sempre como valor fixo (R$)
            $this->semanas->sincronizarFinanceiro($prescricao->refresh(), [
                'desconto_tipo' => 'valor',
                'desconto_valor' => Numero::paraFloat($dados['desconto_valor'] ?? null),
                'adicional_valor' => Numero::paraFloat($dados['adicional_valor'] ?? null),
            ]);

            // Histórico: só registra quando algo mudou de verdade
            $depois = $this->resumoFinanceiro($financeiro->refresh());
            $alteracoes = [];

            foreach ($antes as $campo => $de) {
                $para = $depois[$campo] ?? '—';

                if ($de !== $para) {
                    $alteracoes[] = ['campo' => $campo, 'de' => $de, 'para' => $para];
                }
            }

            if ($alteracoes) {
                PrescricaoLog::registrar($prescricao, TipoLogPrescricao::FinanceiroAjustado,
                    'Financeiro ajustado (parcelas recalculadas).', [
                        'alteracoes' => $alteracoes,
                    ]);
            }
        });

        return redirect()
            ->route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro'])
            ->with('success', 'Financeiro atualizado e parcelas recalculadas.');
    }

    /**
     * Resumo do financeiro (chave => valor) para comparar antes/depois no histórico.
     *
     * @return array<string, string>
     */
    private function resumoFinanceiro(Financeiro $financeiro): array
    {
        return [
            'Valor bruto' => $financeiro->valor_bruto_formatado,
            'Desconto' => (float) $financeiro->valor_desconto > 0
                ? $financeiro->valor_desconto_formatado.' ('.$financeiro->desconto_descricao.')'
                : 'Nenhum',
            'Adicional' => (float) $financeiro->adicional_valor > 0
                ? $financeiro->valor_adicional_formatado
                : 'Nenhum',
            'Total' => $financeiro->valor_total_formatado,
            'Observação' => $financeiro->observacao ?? '—',
        ];
    }
}
