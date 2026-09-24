@extends('layouts.app')

@section('title', 'Relatório — Contas a receber')

@section('content')
  <x-relatorio
    titulo="Contas a receber"
    descricao="Parcelas em aberto (vencidas e a vencer), por paciente e vencimento."
    tabela="Parcelas em aberto ({{ $parcelas->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} (vencimento)">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.contas-receber'),
        'comVencidas' => true,
        'rotuloPeriodo' => 'Vencimento de',
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Parcelas em aberto</small>
          <span class="fw-semibold">{{ $parcelas->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Total em aberto</small>
          <span class="fw-semibold">R$ {{ number_format($totalAberto, 2, ',', '.') }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Total vencido</small>
          <span class="fw-semibold text-danger">R$ {{ number_format($totalVencido, 2, ',', '.') }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Pacientes</small>
          <span class="fw-semibold">
            {{ $parcelas->pluck('semana.prescricao.paciente_id')->filter()->unique()->count() }}
          </span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($parcelas->isEmpty())
      <p class="text-muted mb-0">Nenhuma parcela em aberto com os filtros informados.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 110px;">Vencimento</th>
              <th style="width: 90px;">Situação</th>
              <th style="min-width: 180px;">Paciente</th>
              <th style="width: 140px;">Clínica</th>
              <th style="width: 90px;">Prescrição</th>
              <th style="width: 90px;">Parcela</th>
              <th style="width: 110px;" class="text-end">Valor</th>
              <th style="width: 110px;" class="text-end">Pago</th>
              <th style="width: 120px;" class="text-end">Em aberto</th>
              <th style="width: 100px;">Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($parcelas as $parcela)
              @php
                $vencida = $parcela->vencimento?->lt(now()->startOfDay());
              @endphp

              <tr class="{{ $vencida ? 'table-danger' : '' }}">
                <td class="fw-semibold">{{ $parcela->vencimento_formatado ?? '—' }}</td>
                <td>
                  <span class="badge {{ $vencida ? 'bg-label-danger' : 'bg-label-info' }}">
                    {{ $vencida ? 'Vencida' : 'A vencer' }}
                  </span>
                </td>
                <td>{{ $parcela->semana?->prescricao?->paciente?->nome ?? '—' }}</td>
                <td>{{ $parcela->semana?->prescricao?->clinica?->nome ?? $parcela->financeiro?->clinica?->nome ?? '—' }}</td>
                <td>#{{ $parcela->financeiro?->prescricao_id ?? '—' }}</td>
                <td>{{ $parcela->numero_formatado }}</td>
                <td class="text-end">{{ $parcela->valor_formatado }}</td>
                <td class="text-end">{{ $parcela->valor_pago_formatado }}</td>
                <td class="text-end fw-semibold">{{ $parcela->valor_em_aberto_formatado }}</td>
                <td>
                  <span class="badge {{ $parcela->status?->corBadge() ?? 'bg-label-secondary' }}">
                    {{ $parcela->status?->label() ?? '—' }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="8" class="text-end">Total em aberto</th>
              <th class="text-end">R$ {{ number_format($totalAberto, 2, ',', '.') }}</th>
              <th></th>
            </tr>
            <tr>
              <th colspan="8" class="text-end">Do total, vencido</th>
              <th class="text-end text-danger">R$ {{ number_format($totalVencido, 2, ',', '.') }}</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
    @endif
  </x-relatorio>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
