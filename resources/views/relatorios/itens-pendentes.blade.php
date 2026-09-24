@extends('layouts.app')

@section('title', 'Relatório — Itens pendentes')

@section('content')
  <x-relatorio
    titulo="Itens pendentes"
    descricao="Medicamentos das semanas que ainda não foram aplicados, por data prevista."
    tabela="Itens pendentes ({{ $itens->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} (data prevista)">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.itens-pendentes'),
        'comMedicamento' => true,
        'rotuloPeriodo' => 'Data prevista de',
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Itens pendentes</small>
          <span class="fw-semibold">{{ $itens->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Pacientes</small>
          <span class="fw-semibold">
            {{ $itens->pluck('semana.prescricao.paciente_id')->filter()->unique()->count() }}
          </span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Semanas afetadas</small>
          <span class="fw-semibold">{{ $itens->pluck('prescricao_semana_id')->unique()->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Itens atrasados</small>
          <span class="fw-semibold text-danger">
            {{ $itens->filter(fn ($item) => $item->semana?->data_prevista?->lt(now()->startOfDay()))->count() }}
          </span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($itens->isEmpty())
      <p class="text-muted mb-0">Nenhum item pendente no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 115px;">Data prevista</th>
              <th style="width: 90px;">Semana</th>
              <th style="min-width: 170px;">Paciente</th>
              <th style="width: 140px;">Clínica</th>
              <th style="min-width: 200px;">Medicamento</th>
              <th style="width: 110px;" class="text-end">Quantidade</th>
              <th style="width: 120px;">Situação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($itens as $item)
              @php
                $atrasado = $item->semana?->data_prevista?->lt(now()->startOfDay());
              @endphp

              <tr class="{{ $atrasado ? 'table-warning' : '' }}">
                <td>
                  {{ $item->semana?->data_prevista?->format('d/m/Y') ?? '—' }}
                  @if ($atrasado)
                    <small class="d-block text-danger">atrasado</small>
                  @endif
                </td>
                <td>{{ $item->semana?->numero ?? '—' }}</td>
                <td>{{ $item->semana?->prescricao?->paciente?->nome ?? '—' }}</td>
                <td>{{ $item->semana?->prescricao?->clinica?->nome ?? '—' }}</td>
                <td class="fw-semibold">
                  {{ $item->nome }}
                  @if ($item->tipo === 'combo')
                    <small class="text-body-secondary d-block">combo</small>
                  @endif
                </td>
                <td class="text-end">
                  {{ $item->quantidade_formatada }}@if ($item->eh_miligrama) mg @endif
                </td>
                <td>
                  <span class="badge {{ $item->status?->corBadge() ?? 'bg-label-secondary' }}">
                    {{ $item->status?->label() ?? '—' }}
                  </span>
                </td>
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
