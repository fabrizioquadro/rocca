@extends('layouts.app')

@section('title', 'Relatório — Posição de estoque')

@section('content')
  <x-relatorio
    titulo="Posição de estoque"
    descricao="Saldo fechado (frascos fechados) por medicamento, clínica, lote e vencimento."
    tabela="Saldos em estoque ({{ $linhas->count() }})">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.posicao-estoque'),
        'comPeriodo' => false,
        'comMedicamento' => true,
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Linhas de estoque</small>
          <span class="fw-semibold">{{ $linhas->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Unidades em estoque</small>
          <span class="fw-semibold">{{ $unidades }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Valor em estoque</small>
          <span class="fw-semibold">R$ {{ number_format($valorTotal, 2, ',', '.') }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Lotes vencidos</small>
          <span class="fw-semibold text-danger">{{ $linhas->where('vencido', true)->count() }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($linhas->isEmpty())
      <p class="text-muted mb-0">Nenhum saldo de estoque com os filtros informados.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width: 200px;">Medicamento</th>
              <th style="width: 150px;">Clínica</th>
              <th style="width: 130px;">Código de barras</th>
              <th style="width: 110px;">Lote</th>
              <th style="width: 110px;">Vencimento</th>
              <th style="width: 90px;" class="text-end">Qtde.</th>
              <th style="width: 120px;" class="text-end">Valor unitário</th>
              <th style="width: 130px;" class="text-end">Valor total</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($linhas as $linha)
              <tr class="{{ $linha['vencido'] ? 'table-danger' : '' }}">
                <td class="fw-semibold">{{ $linha['medicamento'] }}</td>
                <td>{{ $linha['clinica'] }}</td>
                <td class="font-monospace">{{ $linha['codigo_barras'] }}</td>
                <td>{{ $linha['lote'] }}</td>
                <td>
                  {{ $linha['vencimento_formatado'] }}
                  @if ($linha['vencido'])
                    <span class="badge bg-label-danger ms-1">vencido</span>
                  @endif
                </td>
                <td class="text-end fw-semibold">{{ $linha['quantidade'] }}</td>
                <td class="text-end">R$ {{ number_format($linha['valor_unitario'], 2, ',', '.') }}</td>
                <td class="text-end fw-semibold">R$ {{ number_format($linha['valor_total'], 2, ',', '.') }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="text-end">Total</th>
              <th class="text-end">{{ $unidades }}</th>
              <th></th>
              <th class="text-end">R$ {{ number_format($valorTotal, 2, ',', '.') }}</th>
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
