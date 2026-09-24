@extends('layouts.app')

@section('title', 'Relatório — Aplicações')

@section('content')
  @php
    $pacientes = $aplicacoes->map(fn ($aplicacao) => $aplicacao->item?->semana?->prescricao?->paciente_id)
        ->filter()
        ->unique()
        ->count();
  @endphp

  <x-relatorio
    titulo="Aplicações"
    descricao="Medicamentos aplicados pela enfermagem, com lote, código de barras e quem aplicou."
    tabela="Aplicações ({{ $aplicacoes->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.aplicacoes'),
        'comMedicamento' => true,
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Aplicações</small>
          <span class="fw-semibold">{{ $aplicacoes->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Mg aplicados (vasilhames)</small>
          <span class="fw-semibold">{{ \App\Support\Numero::formatar($totalMg) }} mg</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Unidades aplicadas (ampolas)</small>
          <span class="fw-semibold">{{ \App\Support\Numero::formatar($totalUnidades) }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Pacientes atendidos</small>
          <span class="fw-semibold">{{ $pacientes }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($aplicacoes->isEmpty())
      <p class="text-muted mb-0">Nenhuma aplicação no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 135px;">Aplicação</th>
              <th style="min-width: 170px;">Paciente</th>
              <th style="width: 110px;">Prescrição</th>
              <th style="min-width: 170px;">Medicamento</th>
              <th style="width: 100px;">Quantidade</th>
              <th style="width: 130px;">Código de barras</th>
              <th style="width: 100px;">Lote</th>
              <th style="width: 110px;">Vencimento</th>
              <th style="min-width: 160px;">Aplicado por</th>
              <th style="width: 140px;">Clínica</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($aplicacoes as $aplicacao)
              <tr>
                <td>{{ $aplicacao->aplicado_em_formatado ?? '—' }}</td>
                <td>{{ $aplicacao->item?->semana?->prescricao?->paciente?->nome ?? '—' }}</td>
                <td>
                  #{{ $aplicacao->item?->semana?->prescricao_id ?? '—' }}
                  <small class="text-muted d-block">semana {{ $aplicacao->item?->semana?->numero ?? '—' }}</small>
                </td>
                <td>
                  {{ $aplicacao->item?->nome ?? '—' }}
                  @if ($aplicacao->item?->tipo === 'combo' && $aplicacao->medicamento)
                    <small class="text-body-secondary d-block">{{ $aplicacao->medicamento->nome }}</small>
                  @endif
                </td>
                <td>{{ $aplicacao->quantidade_com_unidade }}</td>
                <td class="font-monospace">{{ $aplicacao->codigo_barras ?? '—' }}</td>
                <td>{{ $aplicacao->lote ?? '—' }}</td>
                <td>{{ $aplicacao->vencimento_formatado ?? '—' }}</td>
                <td>{{ $aplicacao->user?->nome ?? '—' }}</td>
                <td>{{ $aplicacao->item?->semana?->prescricao?->clinica?->nome ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-relatorio>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
