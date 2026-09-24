<?php

namespace App\Http\Controllers;

use App\Enums\StatusParcela;
use App\Enums\StatusSemanaItem;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\BaixaVasilhameItem;
use App\Models\Clinica;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\FinanceiroPagamento;
use App\Models\FinanceiroParcela;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\PrescricaoSemanaItem;
use App\Models\VasilhameAberto;
use App\Services\RelatorioExportacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Relatórios operacionais: estoque (inclusive vasilhames abertos), aplicações
 * da enfermagem, pendências e financeiro.
 */
class RelatorioController extends Controller
{
    /**
     * Catálogo dos relatórios: slug da rota -> método, título, descrição e ícone.
     * O slug também gera as rotas de exportação (`relatorios.<slug>.pdf` e
     * `relatorios.<slug>.xlsx`).
     */
    public const RELATORIOS = [
        'aplicacoes' => [
            'metodo' => 'aplicacoes',
            'titulo' => 'Aplicações',
            'descricao' => 'Medicamentos aplicados no período, com lote, código de barras e quem aplicou.',
            'icone' => 'ri-syringe-line',
        ],
        'aplicacoes-por-medicamento' => [
            'metodo' => 'aplicacoesPorMedicamento',
            'titulo' => 'Aplicações por medicamento',
            'descricao' => 'Quantidade aplicada, pacientes atendidos e última aplicação por medicamento.',
            'icone' => 'ri-bar-chart-2-line',
        ],
        'vasilhames-abertos' => [
            'metodo' => 'vasilhamesAbertos',
            'titulo' => 'Vasilhames abertos',
            'descricao' => 'Frascos em uso com o saldo em mg que ainda resta em cada um.',
            'icone' => 'ri-archive-2-line',
        ],
        'baixas-abertos' => [
            'metodo' => 'baixasAbertos',
            'titulo' => 'Baixas de medicamentos abertos',
            'descricao' => 'Mg descartados dos frascos abertos, com motivo e vasilhame.',
            'icone' => 'ri-logout-box-r-line',
        ],
        'movimentacoes' => [
            'metodo' => 'movimentacoes',
            'titulo' => 'Movimentações de estoque',
            'descricao' => 'Entradas, saídas, transferências, baixas, consumo e aberturas no período.',
            'icone' => 'ri-swap-box-line',
        ],
        'posicao-estoque' => [
            'metodo' => 'posicaoEstoque',
            'titulo' => 'Posição de estoque',
            'descricao' => 'Saldo fechado por medicamento, clínica, lote e vencimento, com valor.',
            'icone' => 'ri-stack-line',
        ],
        'itens-pendentes' => [
            'metodo' => 'itensPendentes',
            'titulo' => 'Itens pendentes',
            'descricao' => 'Medicamentos das semanas que ainda não foram aplicados.',
            'icone' => 'ri-error-warning-line',
        ],
        'contas-receber' => [
            'metodo' => 'contasReceber',
            'titulo' => 'Contas a receber',
            'descricao' => 'Parcelas em aberto (vencidas e a vencer) por paciente e vencimento.',
            'icone' => 'ri-hand-coin-line',
        ],
        'recebimentos' => [
            'metodo' => 'recebimentos',
            'titulo' => 'Recebimentos',
            'descricao' => 'Pagamentos recebidos no período, por forma de pagamento e usuário.',
            'icone' => 'ri-money-dollar-circle-line',
        ],
        'prescricoes' => [
            'metodo' => 'prescricoes',
            'titulo' => 'Prescrições',
            'descricao' => 'Prescrições do período com valores, desconto, adicional e quanto já foi recebido.',
            'icone' => 'ri-file-list-2-line',
        ],
    ];

    public function __construct(private RelatorioExportacaoService $exportacao)
    {
    }

    /**
     * Índice com os relatórios disponíveis.
     */
    public function index()
    {
        $relatorios = collect(self::RELATORIOS)
            ->map(fn (array $relatorio, string $slug) => $relatorio + ['rota' => "relatorios.{$slug}"])
            ->values();

        return view('relatorios.index', compact('relatorios'));
    }

    /**
     * Exibe o relatório informado pelo slug da rota.
     */
    public function exibir(Request $request, string $relatorio)
    {
        $metodo = $this->definicao($relatorio)['metodo'];

        return $this->{$metodo}($request);
    }

    /**
     * Exporta o relatório em PDF (A4 paisagem).
     */
    public function pdf(Request $request, string $relatorio)
    {
        return $this->exportar($request, $relatorio, 'pdf');
    }

    /**
     * Exporta o relatório em planilha XLSX.
     */
    public function xlsx(Request $request, string $relatorio)
    {
        return $this->exportar($request, $relatorio, 'xlsx');
    }

    /**
     * Gera o arquivo a partir do HTML do próprio relatório: o que está na tela
     * (resumo, filtros aplicados e a tabela) é o que sai no PDF/XLSX.
     */
    private function exportar(Request $request, string $relatorio, string $formato)
    {
        $definicao = $this->definicao($relatorio);

        $html = $this->{$definicao['metodo']}($request)->render();
        $dados = $this->exportacao->extrair($html);

        $arquivo = 'relatorio-'.$relatorio.'-'.now()->format('Y-m-d_Hi');

        return $formato === 'pdf'
            ? $this->exportacao->pdf($definicao['titulo'], $dados, $arquivo.'.pdf')
            : $this->exportacao->xlsx($definicao['titulo'], $dados, $arquivo.'.xlsx');
    }

    /**
     * Definição (método, título, ícone) de um relatório do catálogo.
     *
     * @return array<string, string>
     */
    private function definicao(string $relatorio): array
    {
        abort_unless(isset(self::RELATORIOS[$relatorio]), 404);

        return self::RELATORIOS[$relatorio];
    }

    /**
     * Aplicações do período (uma linha por medicamento aplicado).
     */
    public function aplicacoes(Request $request)
    {
        $aplicacoes = $this->consultaAplicacoes($request);

        $totalMg = round((float) $aplicacoes->whereNotNull('vasilhame_aberto_id')->sum('quantidade'), 3);
        $totalUnidades = round((float) $aplicacoes->whereNull('vasilhame_aberto_id')->sum('quantidade'), 3);

        return view('relatorios.aplicacoes', array_merge(
            $this->dadosComuns($request),
            compact('aplicacoes', 'totalMg', 'totalUnidades')
        ));
    }

    /**
     * Resumo das aplicações agrupado por medicamento.
     */
    public function aplicacoesPorMedicamento(Request $request)
    {
        $linhas = $this->consultaAplicacoes($request)
            ->groupBy('medicamento_id')
            ->map(function ($grupo) {
                return [
                    'medicamento' => $grupo->first()->medicamento?->nome ?? '—',
                    'aplicacoes' => $grupo->count(),
                    'mg' => round((float) $grupo->whereNotNull('vasilhame_aberto_id')->sum('quantidade'), 3),
                    'unidades' => round((float) $grupo->whereNull('vasilhame_aberto_id')->sum('quantidade'), 3),
                    'pacientes' => $grupo->map(fn ($aplicacao) => $aplicacao->item?->semana?->prescricao?->paciente_id)
                        ->filter()
                        ->unique()
                        ->count(),
                    'ultima' => $grupo->max('aplicado_em'),
                ];
            })
            ->sortByDesc('aplicacoes')
            ->values();

        return view('relatorios.aplicacoes-por-medicamento', array_merge(
            $this->dadosComuns($request),
            compact('linhas')
        ));
    }

    /**
     * Vasilhames abertos (em uso) e o saldo em mg de cada um.
     */
    public function vasilhamesAbertos(Request $request)
    {
        $clinicaId = (int) $request->input('clinica_id');
        $medicamentoId = (int) $request->input('medicamento_id');

        $vasilhames = VasilhameAberto::emUso()
            ->with(['medicamento', 'clinica', 'entradaItem', 'abertoPor'])
            ->when($clinicaId, fn ($query) => $query->where('clinica_id', $clinicaId))
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->orderBy('aberto_em')
            ->get();

        $totalMg = round((float) $vasilhames->sum('mg_restantes'), 3);

        return view('relatorios.vasilhames-abertos', array_merge(
            $this->dadosComuns($request),
            compact('vasilhames', 'totalMg')
        ));
    }

    /**
     * Baixas lançadas nos medicamentos abertos (mg descartados).
     */
    public function baixasAbertos(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');
        $medicamentoId = (int) $request->input('medicamento_id');

        $itens = BaixaVasilhameItem::query()
            ->with(['baixa.clinica', 'baixa.user', 'medicamento', 'vasilhameAberto.entradaItem'])
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($clinicaId, fn ($query) => $query->whereHas('baixa', fn ($baixa) => $baixa->where('clinica_id', $clinicaId)))
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->orderBy('created_at')
            ->get();

        $totalMg = round((float) $itens->sum('mg_baixa'), 3);

        return view('relatorios.baixas-abertos', array_merge(
            $this->dadosComuns($request),
            compact('itens', 'totalMg')
        ));
    }

    /**
     * Movimentações de estoque do período.
     */
    public function movimentacoes(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');
        $medicamentoId = (int) $request->input('medicamento_id');
        $tipo = $request->input('tipo');

        $movimentacoes = EstoqueMovimentacao::query()
            ->with(['medicamento', 'clinica', 'user'])
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($clinicaId, fn ($query) => $query->where('clinica_id', $clinicaId))
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->when($tipo, fn ($query) => $query->where('tipo', $tipo))
            ->orderBy('created_at')
            ->get();

        $entradas = (int) $movimentacoes->where('quantidade', '>', 0)->sum('quantidade');
        $saidas = abs((int) $movimentacoes->where('quantidade', '<', 0)->sum('quantidade'));

        return view('relatorios.movimentacoes', array_merge(
            $this->dadosComuns($request),
            compact('movimentacoes', 'entradas', 'saidas', 'tipo')
        ));
    }

    /**
     * Posição de estoque fechado por medicamento, clínica e lote (com valor).
     */
    public function posicaoEstoque(Request $request)
    {
        $clinicaId = (int) $request->input('clinica_id');
        $medicamentoId = (int) $request->input('medicamento_id');

        $saldos = EstoqueMovimentacao::query()
            ->whereNotNull('entrada_item_id')
            ->whereNotNull('clinica_id')
            ->when($clinicaId, fn ($query) => $query->where('clinica_id', $clinicaId))
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->selectRaw('entrada_item_id, medicamento_id, clinica_id, SUM(quantidade) as saldo')
            ->groupBy('entrada_item_id', 'medicamento_id', 'clinica_id')
            ->havingRaw('SUM(quantidade) > 0')
            ->get();

        $itens = EntradaItem::whereIn('id', $saldos->pluck('entrada_item_id'))->get()->keyBy('id');
        $medicamentos = Medicamento::whereIn('id', $saldos->pluck('medicamento_id'))->get()->keyBy('id');
        $clinicas = Clinica::whereIn('id', $saldos->pluck('clinica_id'))->get()->keyBy('id');

        $linhas = $saldos
            ->map(function ($saldo) use ($itens, $medicamentos, $clinicas) {
                $item = $itens->get($saldo->entrada_item_id);
                $quantidade = (int) $saldo->saldo;
                $valorUnitario = (float) ($item?->valor_unitario ?? 0);

                return [
                    'medicamento' => $medicamentos->get($saldo->medicamento_id)?->nome ?? '—',
                    'clinica' => $clinicas->get($saldo->clinica_id)?->nome ?? '—',
                    'codigo_barras' => $item?->codigo_barras ?? '—',
                    'lote' => $item?->lote ?? '—',
                    'vencimento' => $item?->vencimento,
                    'vencimento_formatado' => $item?->vencimento_formatado ?? '—',
                    'vencido' => (bool) $item?->esta_vencido,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => round($quantidade * $valorUnitario, 2),
                ];
            })
            ->sortBy([['medicamento', 'asc'], ['clinica', 'asc'], ['vencimento', 'asc']])
            ->values();

        $unidades = (int) $linhas->sum('quantidade');
        $valorTotal = round((float) $linhas->sum('valor_total'), 2);

        return view('relatorios.posicao-estoque', array_merge(
            $this->dadosComuns($request),
            compact('linhas', 'unidades', 'valorTotal')
        ));
    }

    /**
     * Itens das semanas que ficaram pendentes (não aplicados).
     */
    public function itensPendentes(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');
        $medicamentoId = (int) $request->input('medicamento_id');

        $itens = PrescricaoSemanaItem::query()
            ->with(['medicamento', 'combo', 'semana.prescricao.paciente', 'semana.prescricao.clinica'])
            ->where('gera_aplicacao', true)
            ->where('status', StatusSemanaItem::Pendente->value)
            ->whereHas('semana', function ($query) use ($inicio, $fim, $clinicaId) {
                $query->whereBetween('data_prevista', [$inicio->toDateString(), $fim->toDateString()])
                    ->when($clinicaId, fn ($semana) => $semana->whereHas(
                        'prescricao',
                        fn ($prescricao) => $prescricao->where('clinica_id', $clinicaId)
                    ));
            })
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->get()
            ->sortBy(fn ($item) => $item->semana?->data_prevista)
            ->values();

        return view('relatorios.itens-pendentes', array_merge(
            $this->dadosComuns($request),
            compact('itens')
        ));
    }

    /**
     * Contas a receber: parcelas em aberto (vencidas e a vencer).
     */
    public function contasReceber(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');
        $somenteVencidas = $request->boolean('vencidas');

        $parcelas = FinanceiroParcela::query()
            ->with(['semana.prescricao.paciente', 'semana.prescricao.clinica', 'financeiro'])
            ->whereIn('status', [StatusParcela::Aberta->value, StatusParcela::Parcial->value])
            ->whereBetween('vencimento', [$inicio->toDateString(), $fim->toDateString()])
            ->when($clinicaId, fn ($query) => $query->whereHas(
                'financeiro',
                fn ($financeiro) => $financeiro->where('clinica_id', $clinicaId)
            ))
            ->when($somenteVencidas, fn ($query) => $query->where('vencimento', '<', now()->toDateString()))
            ->orderBy('vencimento')
            ->get();

        $totalAberto = round((float) $parcelas->sum(fn ($parcela) => $parcela->valor_em_aberto), 2);
        $totalVencido = round(
            (float) $parcelas->filter(fn ($parcela) => $parcela->vencimento?->lt(now()->startOfDay()))
                ->sum(fn ($parcela) => $parcela->valor_em_aberto),
            2
        );

        return view('relatorios.contas-receber', array_merge(
            $this->dadosComuns($request),
            compact('parcelas', 'totalAberto', 'totalVencido', 'somenteVencidas')
        ));
    }

    /**
     * Recebimentos do período (pagamentos lançados).
     */
    public function recebimentos(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');

        $pagamentos = FinanceiroPagamento::query()
            ->with(['user', 'financeiro.clinica', 'financeiro.prescricao.paciente'])
            ->whereBetween('data_pagamento', [$inicio->toDateString(), $fim->toDateString()])
            ->when($clinicaId, fn ($query) => $query->whereHas(
                'financeiro',
                fn ($financeiro) => $financeiro->where('clinica_id', $clinicaId)
            ))
            ->orderBy('data_pagamento')
            ->get();

        $totalRecebido = round((float) $pagamentos->sum('valor'), 2);

        $porForma = $pagamentos
            ->groupBy(fn ($pagamento) => $pagamento->forma_descricao)
            ->map(fn ($grupo) => round((float) $grupo->sum('valor'), 2))
            ->sortDesc();

        return view('relatorios.recebimentos', array_merge(
            $this->dadosComuns($request),
            compact('pagamentos', 'totalRecebido', 'porForma')
        ));
    }

    /**
     * Prescrições do período com valores e situação.
     */
    public function prescricoes(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');

        $prescricoes = Prescricao::query()
            ->with(['paciente', 'clinica', 'financeiro'])
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($clinicaId, fn ($query) => $query->where('clinica_id', $clinicaId))
            ->orderByDesc('id')
            ->get();

        $totais = [
            'bruto' => round((float) $prescricoes->sum(fn ($prescricao) => (float) ($prescricao->financeiro?->valor_bruto ?? 0)), 2),
            'desconto' => round((float) $prescricoes->sum(fn ($prescricao) => (float) ($prescricao->financeiro?->valor_desconto ?? 0)), 2),
            'adicional' => round((float) $prescricoes->sum(fn ($prescricao) => (float) ($prescricao->financeiro?->adicional_valor ?? 0)), 2),
            'total' => round((float) $prescricoes->sum(fn ($prescricao) => (float) ($prescricao->financeiro?->valor_total ?? 0)), 2),
            'recebido' => round((float) $prescricoes->sum(fn ($prescricao) => (float) ($prescricao->financeiro?->valor_recebido ?? 0)), 2),
            'aberto' => round((float) $prescricoes->sum(fn ($prescricao) => (float) ($prescricao->financeiro?->valor_aberto ?? 0)), 2),
        ];

        return view('relatorios.prescricoes', array_merge(
            $this->dadosComuns($request),
            compact('prescricoes', 'totais')
        ));
    }

    /**
     * Consulta base das aplicações (usada nos dois relatórios de aplicação).
     */
    private function consultaAplicacoes(Request $request)
    {
        [$inicio, $fim] = $this->periodo($request);
        $clinicaId = (int) $request->input('clinica_id');
        $medicamentoId = (int) $request->input('medicamento_id');

        return PrescricaoSemanaAplicacao::query()
            ->with(['medicamento', 'user', 'item.semana.prescricao.paciente', 'item.semana.prescricao.clinica'])
            ->whereBetween('aplicado_em', [$inicio, $fim])
            ->when($clinicaId, fn ($query) => $query->whereHas(
                'item.semana.prescricao',
                fn ($prescricao) => $prescricao->where('clinica_id', $clinicaId)
            ))
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->orderBy('aplicado_em')
            ->get();
    }

    /**
     * Período do relatório: por padrão, o mês atual.
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
     * Dados que todas as telas de relatório usam (filtros e cabeçalho).
     *
     * @return array<string, mixed>
     */
    private function dadosComuns(Request $request): array
    {
        [$inicio, $fim] = $this->periodo($request);

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'clinicas' => Clinica::orderBy('nome')->get(),
            'medicamentos' => Medicamento::orderBy('nome')->get(),
            'tiposMovimentacao' => TipoMovimentacaoEstoque::opcoes(),
        ];
    }
}
