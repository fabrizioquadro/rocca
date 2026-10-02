@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  @if (session('success') || session('error'))
    <div class="card mb-6">
      <div class="card-body d-flex flex-column gap-2">
        @if (session('success'))
          <div class="alert alert-success mb-0" role="alert">
            {{ session('success') }}
          </div>
        @endif

        @if (session('error'))
          <div class="alert alert-danger mb-0" role="alert">
            {{ session('error') }}
          </div>
        @endif
      </div>
    </div>
  @endif

  @php
    // Atalhos de período (o formulário ao lado aceita qualquer intervalo)
    $atalhos = [
      'Hoje' => [now(), now()],
      '7 dias' => [now()->subDays(6), now()],
      'Este mês' => [now()->startOfMonth(), now()->endOfMonth()],
      'Mês passado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
      'Este ano' => [now()->startOfYear(), now()->endOfYear()],
    ];

    $dinheiro = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
  @endphp

  {{-- Período do resumo: TODOS os cartões abaixo respeitam estas datas --}}
  <div class="card mb-6">
    <div class="card-body">
      <form method="GET" action="{{ route('home') }}">
        <div class="row g-3 align-items-end">
          <div class="col-md-2">
            <label class="form-label" for="inicio">De</label>
            <input type="date" id="inicio" name="inicio" class="form-control" value="{{ $inicio->format('Y-m-d') }}" />
          </div>

          <div class="col-md-2">
            <label class="form-label" for="fim">Até</label>
            <input type="date" id="fim" name="fim" class="form-control" value="{{ $fim->format('Y-m-d') }}" />
          </div>

          <div class="col-md-3">
            <button type="submit" class="btn btn-primary">
              <i class="ri-search-line me-1"></i>Aplicar
            </button>
          </div>

          <div class="col-md-5">
            <div class="d-flex flex-wrap justify-content-md-end gap-2">
              @foreach ($atalhos as $rotulo => [$de, $ate])
                <a
                  href="{{ route('home', ['inicio' => $de->format('Y-m-d'), 'fim' => $ate->format('Y-m-d')]) }}"
                  class="btn btn-sm {{ $inicio->isSameDay($de) && $fim->isSameDay($ate) ? 'btn-primary' : 'btn-outline-primary' }}">
                  {{ $rotulo }}
                </a>
              @endforeach
            </div>
          </div>
        </div>

        <small class="text-muted d-block mt-2">
          Comparando com o período anterior: {{ $anteriorInicio->format('d/m/Y') }} a {{ $anteriorFim->format('d/m/Y') }}
        </small>
      </form>
    </div>
  </div>

  {{-- ============================ FINANCEIRO ============================ --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <i class="ri-money-dollar-circle-line ri-22px text-primary"></i>
      <h5 class="fw-semibold mb-0">Financeiro</h5>
      <span class="text-muted small">{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}</span>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('relatorios.recebimentos', ['inicio' => $inicio->format('Y-m-d'), 'fim' => $fim->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-primary">
        <i class="ri-arrow-right-up-line me-1"></i>Recebimentos
      </a>

      <a href="{{ route('relatorios.contas-receber') }}" class="btn btn-sm btn-outline-primary">
        <i class="ri-arrow-right-up-line me-1"></i>Contas a receber
      </a>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <x-kpi
      rotulo="Recebido no período"
      :valor="$dinheiro($recebido)"
      icone="ri-hand-coin-line"
      cor="success"
      :variacao="$variacaoRecebido"
      detalhe="pagamentos lançados no período" />

    <x-kpi
      rotulo="Faturado no período"
      :valor="$dinheiro($faturado)"
      icone="ri-file-list-3-line"
      cor="primary"
      :variacao="$variacaoFaturado"
      detalhe="vencimento das parcelas" />

    <x-kpi
      rotulo="Saldo do período"
      :valor="$dinheiro($saldoPeriodo)"
      icone="ri-scales-3-line"
      :cor="$saldoPeriodo <= 0 ? 'success' : 'warning'"
      :detalhe="$saldoPeriodo <= 0 ? 'recebido cobre o faturado' : 'faturado menos recebido'" />

    <x-kpi
      rotulo="Vencido em aberto"
      :valor="$dinheiro($vencido)"
      icone="ri-alarm-warning-line"
      cor="danger"
      detalhe="parcelas vencidas de todos os períodos" />
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 class="fw-semibold mb-0">Recebimentos</h6>
          <span class="text-muted small">Total de {{ $dinheiro($recebido) }} no período</span>
        </div>

        <div class="card-body">
          @if ($recebido > 0)
            <div id="grafico-recebimentos" style="min-height: 280px;"></div>
          @else
            <p class="text-muted mb-0">Nenhum recebimento lançado no período.</p>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="fw-semibold mb-0">Por forma de pagamento</h6>
        </div>

        <div class="card-body">
          @if ($porForma->isNotEmpty())
            <div id="grafico-formas" style="min-height: 280px;"></div>
          @else
            <p class="text-muted mb-0">Sem recebimentos no período.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <div class="card-header">
      <h6 class="fw-semibold mb-0">Financeiro por clínica</h6>
    </div>

    <div class="card-body">
      @if ($porClinica->isEmpty())
        <p class="text-muted mb-0">Nada faturado nem recebido no período.</p>
      @else
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Clínica</th>
                <th class="text-end" style="width: 160px;">Faturado</th>
                <th class="text-end" style="width: 160px;">Recebido</th>
                <th class="text-end" style="width: 160px;">Saldo</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($porClinica as $linha)
                <tr>
                  <td>{{ $linha['clinica'] }}</td>
                  <td class="text-end">{{ $dinheiro($linha['faturado']) }}</td>
                  <td class="text-end text-success">{{ $dinheiro($linha['recebido']) }}</td>
                  <td class="text-end {{ $linha['saldo'] > 0 ? 'text-danger' : 'text-muted' }}">
                    {{ $dinheiro($linha['saldo']) }}
                  </td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <th class="text-end">Total</th>
                <th class="text-end">{{ $dinheiro($faturado) }}</th>
                <th class="text-end text-success">{{ $dinheiro($recebido) }}</th>
                <th class="text-end">{{ $dinheiro($saldoPeriodo) }}</th>
              </tr>
            </tfoot>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- ============================ PRESCRIÇÕES ============================ --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <i class="ri-file-list-2-line ri-22px text-primary"></i>
      <h5 class="fw-semibold mb-0">Prescrições e atendimentos</h5>
      <span class="text-muted small">prescrições cadastradas no período</span>
    </div>

    <a href="{{ route('relatorios.prescricoes', ['inicio' => $inicio->format('Y-m-d'), 'fim' => $fim->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-primary">
      <i class="ri-arrow-right-up-line me-1"></i>Relatório de prescrições
    </a>
  </div>

  <div class="row g-4 mb-4">
    <x-kpi
      rotulo="Prescrições"
      :valor="number_format($totalPrescricoes, 0, ',', '.')"
      icone="ri-file-list-2-line"
      cor="primary"
      detalhe="cadastradas no período" />

    <x-kpi
      rotulo="Pacientes"
      :valor="number_format($totalPacientes, 0, ',', '.')"
      icone="ri-user-heart-line"
      cor="info"
      detalhe="pacientes distintos" />

    <x-kpi
      rotulo="Ticket médio"
      :valor="$dinheiro($ticketMedio)"
      icone="ri-price-tag-3-line"
      cor="warning"
      detalhe="valor médio por prescrição" />

    <x-kpi
      rotulo="Aplicações realizadas"
      :valor="number_format($totalAplicacoes, 0, ',', '.')"
      icone="ri-syringe-line"
      cor="success"
      detalhe="registros de aplicação no período" />
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-5">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="fw-semibold mb-0">Prescrições por médico</h6>
        </div>

        <div class="card-body">
          @if ($prescricoesPorMedico->isNotEmpty())
            <div id="grafico-medicos" style="min-height: 300px;"></div>
          @else
            <p class="text-muted mb-0">Nenhuma prescrição no período.</p>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-7">
      <div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 class="fw-semibold mb-0">Resumo por médico</h6>
          <span class="text-muted small">{{ $prescricoesPorMedico->count() }} médico(s)</span>
        </div>

        <div class="card-body">
          @if ($prescricoesPorMedico->isEmpty())
            <p class="text-muted mb-0">Nenhuma prescrição no período.</p>
          @else
            <div class="table-responsive" style="max-height: 300px;">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Médico</th>
                    <th class="text-center" style="width: 110px;">Prescrições</th>
                    <th class="text-center" style="width: 110px;">Pacientes</th>
                    <th class="text-end" style="width: 140px;">Valor total</th>
                    <th class="text-end" style="width: 140px;">Recebido</th>
                    <th class="text-end" style="width: 140px;">Em aberto</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($prescricoesPorMedico as $linha)
                    <tr>
                      <td>{{ $linha['medico'] }}</td>
                      <td class="text-center fw-semibold">{{ $linha['prescricoes'] }}</td>
                      <td class="text-center">{{ $linha['pacientes'] }}</td>
                      <td class="text-end">{{ $dinheiro($linha['total']) }}</td>
                      <td class="text-end text-success">{{ $dinheiro($linha['recebido']) }}</td>
                      <td class="text-end {{ $linha['aberto'] > 0 ? 'text-danger' : 'text-muted' }}">
                        {{ $dinheiro($linha['aberto']) }}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot>
                  <tr>
                    <th class="text-end">Total</th>
                    <th class="text-center">{{ $totalPrescricoes }}</th>
                    <th class="text-center">{{ $totalPacientes }}</th>
                    <th class="text-end">{{ $dinheiro($prescricoesPorMedico->sum('total')) }}</th>
                    <th class="text-end text-success">{{ $dinheiro($prescricoesPorMedico->sum('recebido')) }}</th>
                    <th class="text-end text-danger">{{ $dinheiro($prescricoesPorMedico->sum('aberto')) }}</th>
                  </tr>
                </tfoot>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- ===================== UTILIZAÇÃO DE MEDICAMENTOS ===================== --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <i class="ri-capsule-line ri-22px text-primary"></i>
      <h5 class="fw-semibold mb-0">Utilização de medicamentos</h5>
      <span class="text-muted small">aplicações registradas no período</span>
    </div>

    <a href="{{ route('relatorios.aplicacoes-por-medicamento', ['inicio' => $inicio->format('Y-m-d'), 'fim' => $fim->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-primary">
      <i class="ri-arrow-right-up-line me-1"></i>Relatório por medicamento
    </a>
  </div>

  <div class="card mb-6">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h6 class="fw-semibold mb-0">Medicamentos mais aplicados</h6>
      <span class="text-muted small">mg dos vasilhames e unidades em colunas separadas</span>
    </div>

    <div class="card-body">
      @if ($aplicacoesPorMedicamento->isEmpty())
        <p class="text-muted mb-0">Nenhuma aplicação registrada no período.</p>
      @else
        <div class="row g-4">
          <div class="col-xl-5">
            <div id="grafico-medicamentos" style="min-height: 320px;"></div>
          </div>

          <div class="col-xl-7">
            <div class="table-responsive" style="max-height: 340px;">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Medicamento</th>
                    <th class="text-center" style="width: 120px;">Aplicações</th>
                    <th class="text-center" style="width: 120px;">Unidades</th>
                    <th class="text-center" style="width: 120px;">mg</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($aplicacoesPorMedicamento as $linha)
                    <tr>
                      <td>{{ $linha['medicamento'] }}</td>
                      <td class="text-center fw-semibold">{{ $linha['aplicacoes'] }}</td>
                      <td class="text-center">{{ $linha['unidades'] > 0 ? $linha['unidades'] : '—' }}</td>
                      <td class="text-center">
                        {{ $linha['mg'] > 0 ? \App\Support\Numero::formatar($linha['mg']) : '—' }}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot>
                  <tr>
                    <th class="text-end">Total</th>
                    <th class="text-center">{{ $aplicacoesPorMedicamento->sum('aplicacoes') }}</th>
                    <th class="text-center">{{ $aplicacoesPorMedicamento->sum('unidades') }}</th>
                    <th class="text-center">
                      {{ \App\Support\Numero::formatar($aplicacoesPorMedicamento->sum('mg')) }}
                    </th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>

  {{-- ============================ OPERACIONAL ============================ --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <i class="ri-live-line ri-22px text-primary"></i>
      <h5 class="fw-semibold mb-0">Agora e nos próximos dias</h5>
      <span class="text-muted small">não depende do filtro de datas</span>
    </div>

    <a href="{{ route('enfermagem.index') }}" class="btn btn-sm btn-outline-primary">
      <i class="ri-nurse-line me-1"></i>Ir para a Enfermagem
    </a>
  </div>

  <div class="row g-4 mb-4">
    <x-kpi
      rotulo="Na fila de aplicação"
      :valor="number_format($naFila, 0, ',', '.')"
      icone="ri-user-follow-line"
      cor="info"
      detalhe="pacientes esperando o atendimento" />

    <x-kpi
      rotulo="Em atendimento"
      :valor="number_format($emAtendimento, 0, ',', '.')"
      icone="ri-heart-pulse-line"
      cor="warning"
      detalhe="atendimentos abertos agora" />

    <x-kpi
      rotulo="Semanas em atraso"
      :valor="number_format($semanasEmAtraso, 0, ',', '.')"
      icone="ri-calendar-close-line"
      cor="danger"
      detalhe="agendadas para dias que já passaram" />

    <x-kpi
      rotulo="Estoque em alerta"
      :valor="number_format($estoqueAbaixoMedio + $estoqueAbaixoMinimo, 0, ',', '.')"
      icone="ri-error-warning-line"
      :cor="$estoqueAbaixoMinimo > 0 ? 'danger' : 'warning'"
      :detalhe="$estoqueAbaixoMinimo.' abaixo do mínimo · '.$estoqueAbaixoMedio.' abaixo do médio'" />
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h6 class="fw-semibold mb-0">Agenda dos próximos 7 dias</h6>
      <span class="text-muted small">semanas agendadas por dia</span>
    </div>

    <div class="card-body">
      <div id="grafico-agenda" style="min-height: 240px;"></div>
    </div>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
  <script>
    $(function () {
      // Paleta do sistema: o marrom do tema como base, com tons derivados
      const CORES = ['#550000', '#a03a48', '#cf7f8a', '#e8b6bd', '#8c8c8c', '#c9a227'];
      const FONTE = 'Inter, Arial, sans-serif';

      const dinheiro = (valor) => 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      const dinheiroCurto = (valor) => 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 0
      });

      const base = {
        chart: {
          fontFamily: FONTE,
          toolbar: { show: false },
          animations: { speed: 300 }
        },
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 3 },
        noData: { text: 'Sem dados no período' }
      };

      // Recebimentos: linha/área por dia (ou mês, quando o período é longo)
      const recebimentos = document.querySelector('#grafico-recebimentos');
      if (recebimentos) {
        new ApexCharts(recebimentos, Object.assign({}, base, {
          chart: Object.assign({}, base.chart, { type: 'area', height: 280 }),
          series: [{ name: 'Recebido', data: @json($serieValores) }],
          xaxis: {
            categories: @json($serieRotulos),
            labels: { rotate: -45, rotateAlways: false, hideOverlappingLabels: true }
          },
          yaxis: { labels: { formatter: dinheiroCurto } },
          colors: ['#550000'],
          stroke: { curve: 'smooth', width: 2 },
          fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
          tooltip: { y: { formatter: dinheiro } }
        })).render();
      }

      // Formas de pagamento: rosca
      const formas = document.querySelector('#grafico-formas');
      if (formas) {
        new ApexCharts(formas, Object.assign({}, base, {
          chart: Object.assign({}, base.chart, { type: 'donut', height: 280 }),
          series: @json($porForma->values()),
          labels: @json($porForma->keys()),
          colors: CORES,
          legend: { position: 'bottom' },
          plotOptions: { pie: { donut: { size: '62%' } } },
          tooltip: { y: { formatter: dinheiro } }
        })).render();
      }

      // Prescrições por médico: barras horizontais (top 10)
      const medicos = document.querySelector('#grafico-medicos');
      if (medicos) {
        const linhas = @json($topMedicos);

        new ApexCharts(medicos, Object.assign({}, base, {
          chart: Object.assign({}, base.chart, { type: 'bar', height: 300 }),
          series: [{ name: 'Prescrições', data: linhas.map((linha) => linha.prescricoes) }],
          xaxis: { categories: linhas.map((linha) => linha.medico) },
          colors: ['#550000'],
          plotOptions: {
            bar: { horizontal: true, borderRadius: 4, barHeight: '60%' }
          },
          tooltip: {
            y: {
              formatter: (valor, opcoes) => {
                const linha = linhas[opcoes.dataPointIndex];

                return valor + ' prescrição(ões) · ' + linha.pacientes + ' paciente(s) · ' + dinheiro(linha.total);
              }
            }
          }
        })).render();
      }

      // Medicamentos mais aplicados: barras horizontais (top 10)
      const medicamentos = document.querySelector('#grafico-medicamentos');
      if (medicamentos) {
        const linhas = @json($topMedicamentos);

        new ApexCharts(medicamentos, Object.assign({}, base, {
          chart: Object.assign({}, base.chart, { type: 'bar', height: 320 }),
          series: [{ name: 'Aplicações', data: linhas.map((linha) => linha.aplicacoes) }],
          xaxis: { categories: linhas.map((linha) => linha.medicamento) },
          colors: ['#a03a48'],
          plotOptions: {
            bar: { horizontal: true, borderRadius: 4, barHeight: '60%' }
          },
          tooltip: {
            y: {
              formatter: (valor, opcoes) => {
                const linha = linhas[opcoes.dataPointIndex];
                const partes = [valor + ' aplicação(ões)'];

                if (linha.unidades > 0) partes.push(linha.unidades + ' unidade(s)');
                if (linha.mg > 0) partes.push(linha.mg.toLocaleString('pt-BR') + ' mg');

                return partes.join(' · ');
              }
            }
          }
        })).render();
      }

      // Agenda dos próximos 7 dias
      const agenda = document.querySelector('#grafico-agenda');
      if (agenda) {
        new ApexCharts(agenda, Object.assign({}, base, {
          chart: Object.assign({}, base.chart, { type: 'bar', height: 240 }),
          series: [{ name: 'Semanas agendadas', data: @json($agendaValores) }],
          xaxis: { categories: @json($agendaRotulos) },
          colors: ['#cf7f8a'],
          plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
          tooltip: { y: { formatter: (valor) => valor + ' semana(s)' } }
        })).render();
      }
    });
  </script>
@endpush
