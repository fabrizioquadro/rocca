@extends('layouts.app')

@section('title', 'Relatório — Movimentações de estoque')

@section('content')
  <x-relatorio
    titulo="Movimentações de estoque"
    descricao="Entradas, saídas, transferências, baixas, consumo e aberturas de vasilhame no período."
    tabela="Movimentações ({{ $movimentacoes->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.movimentacoes'),
        'comMedicamento' => true,
        'comTipo' => true,
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Lançamentos</small>
          <span class="fw-semibold">{{ $movimentacoes->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Entradas (unidades)</small>
          <span class="fw-semibold text-success">+{{ $entradas }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Saídas (unidades)</small>
          <span class="fw-semibold text-danger">-{{ $saidas }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Saldo do período</small>
          <span class="fw-semibold">{{ $entradas - $saidas }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($movimentacoes->isEmpty())
      <p class="text-muted mb-0">Nenhuma movimentação no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 135px;">Data</th>
              <th style="width: 150px;">Tipo</th>
              <th style="min-width: 200px;">Medicamento</th>
              <th style="width: 140px;">Clínica</th>
              <th style="width: 120px;">Lote</th>
              <th style="width: 130px;">Código de barras</th>
              <th style="width: 90px;" class="text-end">Qtde.</th>
              <th style="min-width: 150px;">Usuário</th>
              <th style="min-width: 180px;">Observação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($movimentacoes as $movimentacao)
              <tr>
                <td>{{ $movimentacao->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>
                  <span class="badge bg-label-{{ $movimentacao->tipo?->corBadge() ?? 'secondary' }}">
                    {{ $movimentacao->tipo?->label() ?? '—' }}
                  </span>
                </td>
                <td class="fw-semibold">{{ $movimentacao->medicamento?->nome ?? '—' }}</td>
                <td>{{ $movimentacao->clinica?->nome ?? '—' }}</td>
                <td>{{ $movimentacao->entradaItem?->lote ?? '—' }}</td>
                <td class="font-monospace">{{ $movimentacao->entradaItem?->codigo_barras ?? '—' }}</td>
                <td class="text-end fw-semibold {{ $movimentacao->quantidade < 0 ? 'text-danger' : 'text-success' }}">
                  {{ $movimentacao->quantidade_com_sinal }}
                </td>
                <td>{{ $movimentacao->user?->nome ?? '—' }}</td>
                <td class="small text-body-secondary">{{ $movimentacao->observacao ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="6" class="text-end">Entradas / Saídas</th>
              <th class="text-end">
                <span class="text-success">+{{ $entradas }}</span> /
                <span class="text-danger">-{{ $saidas }}</span>
              </th>
              <th colspan="2"></th>
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
