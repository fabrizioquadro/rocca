<?php

namespace App\Http\Controllers;

use App\Enums\StatusParcela;
use App\Enums\StatusSemana;
use App\Models\Clinica;
use App\Models\FinanceiroPagamento;
use App\Models\FinanceiroParcela;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\PrescricaoSemanaAtendimento;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dashboard: resumo do período escolhido — financeiro, prescrições, utilização
 * de medicamentos e alertas operacionais.
 *
 * Tudo respeita o filtro de datas do topo (por padrão, o mês atual). Os blocos
 * ficam em métodos privados para a página continuar legível.
 */
class DashboardController extends Controller
{
    /**
     * Página inicial do sistema.
     */
    public function index(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);

        return view('home', array_merge(
            [
                'inicio' => $inicio,
                'fim' => $fim,
                'anteriorInicio' => $this->inicioDoPeriodoAnterior($inicio, $fim),
                'anteriorFim' => $inicio->copy()->subDay()->endOfDay(),
            ],
            $this->blocoFinanceiro($inicio, $fim),
            $this->blocoPrescricoes($inicio, $fim),
            $this->blocoUtilizacao($inicio, $fim),
            $this->blocoOperacional()
        ));
    }

    /**
     * Período do resumo: por padrão, o mês atual (mesma regra dos relatórios).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodo(Request $request): array
    {
        $dados = $request->validate([
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date'],
        ], [
            'inicio.date' => 'Informe uma data inicial válida.',
            'fim.date' => 'Informe uma data final válida.',
        ]);

        $inicio = filled($dados['inicio'] ?? null)
            ? Carbon::parse($dados['inicio'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $fim = filled($dados['fim'] ?? null)
            ? Carbon::parse($dados['fim'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        return [$inicio, $fim];
    }

    /**
     * Início do período anterior de mesma duração (base da comparação dos KPIs).
     */
    private function inicioDoPeriodoAnterior(Carbon $inicio, Carbon $fim): Carbon
    {
        $dias = (int) $inicio->diffInDays($fim) + 1;

        return $inicio->copy()->subDays($dias);
    }

    /**
     * Variação percentual em relação ao período anterior (null quando não há base).
     */
    private function variacao(float $atual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * Bloco financeiro: faturado (vencimento das parcelas), recebido (data do
     * pagamento), saldo, vencidos, série de recebimentos, formas de pagamento e
     * o resumo por clínica.
     *
     * @return array<string, mixed>
     */
    private function blocoFinanceiro(Carbon $inicio, Carbon $fim): array
    {
        $faturado = (float) FinanceiroParcela::query()
            ->whereBetween('vencimento', [$inicio->toDateString(), $fim->toDateString()])
            ->sum('valor');

        $pagamentos = FinanceiroPagamento::query()
            ->whereBetween('data_pagamento', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data_pagamento')
            ->get();

        $recebido = round((float) $pagamentos->sum('valor'), 2);

        // Parcelas em aberto com vencimento no passado (não só as do período)
        $vencido = (float) FinanceiroParcela::query()
            ->whereIn('status', [StatusParcela::Aberta->value, StatusParcela::Parcial->value])
            ->where('vencimento', '<', now()->toDateString())
            ->selectRaw('COALESCE(SUM(valor - valor_pago), 0) as aberto')
            ->value('aberto');

        // Período anterior (para mostrar a variação dos KPIs)
        $anteriorInicio = $this->inicioDoPeriodoAnterior($inicio, $fim);
        $anteriorFim = $inicio->copy()->subDay()->endOfDay();

        $faturadoAnterior = (float) FinanceiroParcela::query()
            ->whereBetween('vencimento', [$anteriorInicio->toDateString(), $anteriorFim->toDateString()])
            ->sum('valor');

        $recebidoAnterior = (float) FinanceiroPagamento::query()
            ->whereBetween('data_pagamento', [$anteriorInicio->toDateString(), $anteriorFim->toDateString()])
            ->sum('valor');

        [$serieRotulos, $serieValores] = $this->serieDeRecebimentos($inicio, $fim, $pagamentos);

        $porForma = $pagamentos
            ->groupBy(fn (FinanceiroPagamento $pagamento) => $pagamento->forma_pagamento?->label() ?? 'Não informada')
            ->map(fn ($grupo) => round((float) $grupo->sum('valor'), 2))
            ->sortDesc();

        return [
            'faturado' => round($faturado, 2),
            'recebido' => $recebido,
            'saldoPeriodo' => round($faturado - $recebido, 2),
            'vencido' => round($vencido, 2),
            'variacaoFaturado' => $this->variacao($faturado, $faturadoAnterior),
            'variacaoRecebido' => $this->variacao($recebido, $recebidoAnterior),
            'serieRotulos' => $serieRotulos,
            'serieValores' => $serieValores,
            'porForma' => $porForma,
            'porClinica' => $this->financeiroPorClinica($inicio, $fim),
        ];
    }

    /**
     * Recebimentos por dia (períodos curtos) ou por mês (períodos longos), sem
     * buracos: todo dia/mês do período aparece, mesmo com valor zero.
     *
     * @param  \Illuminate\Support\Collection<int, FinanceiroPagamento>  $pagamentos
     * @return array{0: list<string>, 1: list<float>}
     */
    private function serieDeRecebimentos(Carbon $inicio, Carbon $fim, $pagamentos): array
    {
        $porMes = $inicio->diffInDays($fim) > 62;

        $rotulos = [];
        $valores = [];

        if ($porMes) {
            $agrupado = $pagamentos->groupBy(fn ($pagamento) => $pagamento->data_pagamento->format('Y-m'));
            $cursor = $inicio->copy()->startOfMonth();

            while ($cursor <= $fim) {
                $rotulos[] = $cursor->format('m/Y');
                $valores[] = round((float) ($agrupado[$cursor->format('Y-m')] ?? collect())->sum('valor'), 2);
                $cursor->addMonth();
            }

            return [$rotulos, $valores];
        }

        $agrupado = $pagamentos->groupBy(fn ($pagamento) => $pagamento->data_pagamento->format('Y-m-d'));
        $cursor = $inicio->copy()->startOfDay();

        while ($cursor <= $fim) {
            $rotulos[] = $cursor->format('d/m');
            $valores[] = round((float) ($agrupado[$cursor->format('Y-m-d')] ?? collect())->sum('valor'), 2);
            $cursor->addDay();
        }

        return [$rotulos, $valores];
    }

    /**
     * Faturado (vencimento) e recebido (pagamento) do período, por clínica.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function financeiroPorClinica(Carbon $inicio, Carbon $fim)
    {
        $faturado = FinanceiroParcela::query()
            ->join('financeiros', 'financeiros.id', '=', 'financeiro_parcelas.financeiro_id')
            ->whereBetween('financeiro_parcelas.vencimento', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('financeiros.clinica_id, SUM(financeiro_parcelas.valor) as total')
            ->groupBy('financeiros.clinica_id')
            ->pluck('total', 'clinica_id');

        $recebido = FinanceiroPagamento::query()
            ->join('financeiros', 'financeiros.id', '=', 'financeiro_pagamentos.financeiro_id')
            ->whereBetween('financeiro_pagamentos.data_pagamento', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('financeiros.clinica_id, SUM(financeiro_pagamentos.valor) as total')
            ->groupBy('financeiros.clinica_id')
            ->pluck('total', 'clinica_id');

        $linhas = Clinica::orderBy('nome')->get()->map(fn (Clinica $clinica) => [
            'clinica' => $clinica->nome,
            'faturado' => round((float) ($faturado[$clinica->id] ?? 0), 2),
            'recebido' => round((float) ($recebido[$clinica->id] ?? 0), 2),
        ]);

        // Financeiro sem clínica também precisa aparecer (senão o total não fecha)
        $semClinicaFaturado = round((float) ($faturado[''] ?? 0), 2);
        $semClinicaRecebido = round((float) ($recebido[''] ?? 0), 2);

        if ($semClinicaFaturado > 0 || $semClinicaRecebido > 0) {
            $linhas->push([
                'clinica' => 'Sem clínica',
                'faturado' => $semClinicaFaturado,
                'recebido' => $semClinicaRecebido,
            ]);
        }

        return $linhas
            ->map(function (array $linha) {
                $linha['saldo'] = round($linha['faturado'] - $linha['recebido'], 2);

                return $linha;
            })
            ->filter(fn (array $linha) => $linha['faturado'] > 0 || $linha['recebido'] > 0)
            ->sortByDesc('faturado')
            ->values();
    }

    /**
     * Bloco de prescrições: totais do período, ticket médio e o resumo por médico.
     *
     * @return array<string, mixed>
     */
    private function blocoPrescricoes(Carbon $inicio, Carbon $fim): array
    {
        $prescricoes = Prescricao::with(['semanas', 'financeiro.parcelas', 'financeiro.pagamentos'])
            ->whereBetween('created_at', [$inicio, $fim])
            ->get();

        $porMedico = $prescricoes
            ->groupBy(fn (Prescricao $prescricao) => filled($prescricao->medico_nome) ? $prescricao->medico_nome : 'Sem médico')
            ->map(fn ($grupo, $medico) => [
                'medico' => $medico,
                'prescricoes' => $grupo->count(),
                'pacientes' => $grupo->pluck('paciente_id')->filter()->unique()->count(),
                'total' => round((float) $grupo->sum(fn (Prescricao $prescricao) => (float) ($prescricao->financeiro?->valor_total ?? $prescricao->valor_total)), 2),
                'recebido' => round((float) $grupo->sum(fn (Prescricao $prescricao) => (float) ($prescricao->financeiro?->valor_recebido ?? 0)), 2),
            ])
            ->map(function (array $linha) {
                $linha['aberto'] = round($linha['total'] - $linha['recebido'], 2);

                return $linha;
            })
            ->sortByDesc('prescricoes')
            ->values();

        $totalValor = round((float) $porMedico->sum('total'), 2);

        return [
            'totalPrescricoes' => $prescricoes->count(),
            'totalPacientes' => $prescricoes->pluck('paciente_id')->filter()->unique()->count(),
            'ticketMedio' => $prescricoes->count() > 0 ? round($totalValor / $prescricoes->count(), 2) : 0.0,
            'prescricoesPorMedico' => $porMedico,
            'topMedicos' => $porMedico->take(10)->values(),
        ];
    }

    /**
     * Bloco de utilização: aplicações registradas no período por medicamento.
     *
     * @return array<string, mixed>
     */
    private function blocoUtilizacao(Carbon $inicio, Carbon $fim): array
    {
        $aplicacoes = PrescricaoSemanaAplicacao::with('medicamento')
            ->whereBetween('aplicado_em', [$inicio, $fim])
            ->get();

        $porMedicamento = $aplicacoes
            ->groupBy(fn (PrescricaoSemanaAplicacao $aplicacao) => $aplicacao->medicamento?->nome ?? 'Medicamento removido')
            ->map(fn ($grupo, $medicamento) => [
                'medicamento' => $medicamento,
                'aplicacoes' => $grupo->count(),
                // mg (vasilhame aberto) e unidades (ampola/combo) são grandezas diferentes
                'unidades' => (int) round((float) $grupo->whereNull('vasilhame_aberto_id')->sum('quantidade')),
                'mg' => round((float) $grupo->whereNotNull('vasilhame_aberto_id')->sum('quantidade'), 2),
            ])
            ->sortByDesc('aplicacoes')
            ->values();

        return [
            'totalAplicacoes' => $aplicacoes->count(),
            'aplicacoesPorMedicamento' => $porMedicamento,
            'topMedicamentos' => $porMedicamento->take(10)->values(),
        ];
    }

    /**
     * Alertas operacionais: fila, atendimentos, semanas em atraso, estoque baixo
     * e a agenda dos próximos 7 dias.
     *
     * @return array<string, mixed>
     */
    private function blocoOperacional(): array
    {
        $naFila = PrescricaoSemana::query()
            ->whereIn('status', [StatusSemana::FilaAplicacao, StatusSemana::AplicacaoParcial])
            ->whereDoesntHave('atendimentos', fn ($query) => $query->whereNull('finalizado_em'))
            ->count();

        $emAtendimento = PrescricaoSemanaAtendimento::query()->whereNull('finalizado_em')->count();

        $semanasEmAtraso = PrescricaoSemana::query()
            ->where('status', StatusSemana::Agendada)
            ->whereDate('data_prevista', '<', now()->toDateString())
            ->count();

        // Estoque: quantos medicamentos estão abaixo do nível cadastrado
        $niveis = Medicamento::query()
            ->withSum('movimentacoes as saldo', 'quantidade')
            ->get()
            ->map(fn (Medicamento $medicamento) => $medicamento->nivel_estoque)
            ->countBy();

        // Agenda: semanas agendadas em cada um dos próximos 7 dias
        $dias = collect(range(0, 6))->map(fn (int $indice) => now()->startOfDay()->addDays($indice));

        $agendadas = PrescricaoSemana::query()
            ->where('status', StatusSemana::Agendada)
            ->whereBetween('data_prevista', [$dias->first()->toDateString(), $dias->last()->toDateString()])
            ->get(['data_prevista'])
            ->groupBy(fn (PrescricaoSemana $semana) => $semana->data_prevista?->format('Y-m-d'));

        return [
            'naFila' => $naFila,
            'emAtendimento' => $emAtendimento,
            'semanasEmAtraso' => $semanasEmAtraso,
            'estoqueAbaixoMinimo' => (int) $niveis->get('minimo', 0),
            'estoqueAbaixoMedio' => (int) $niveis->get('medio', 0),
            'agendaRotulos' => $dias->map(fn (Carbon $dia) => $dia->format('d/m'))->all(),
            'agendaValores' => $dias->map(fn (Carbon $dia) => ($agendadas[$dia->format('Y-m-d')] ?? collect())->count())->all(),
        ];
    }
}
