@extends('layouts.app')

@section('title', 'Relatório — Baixas de medicamentos abertos')

@section('content')
  <x-relatorio
    titulo="Baixas de medicamentos abertos"
    descricao="Mg descartados dos frascos abertos (perda, quebra, vencimento ou sobra)."
    tabela="Baixas do período ({{ $itens->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.baixas-abertos'),
        'comMedicamento' => true,
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Lançamentos de baixa</small>
          <span class="fw-semibold">{{ $itens->pluck('baixa_vasilhame_id')->unique()->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Vasilhames baixados</small>
          <span class="fw-semibold">{{ $itens->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Total baixado</small>
          <span class="fw-semibold">{{ \App\Support\Numero::formatar($totalMg) }} mg</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Clínicas</small>
          <span class="fw-semibold">{{ $itens->pluck('baixa.clinica_id')->filter()->unique()->count() }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($itens->isEmpty())
      <p class="text-muted mb-0">Nenhuma baixa de medicamento aberto no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 135px;">Data</th>
              <th style="width: 80px;">Baixa</th>
              <th style="min-width: 190px;">Medicamento</th>
              <th style="width: 130px;">Código de barras</th>
              <th style="width: 100px;">Lote</th>
              <th style="width: 130px;" class="text-end">Baixado (mg)</th>
              <th style="width: 140px;">Clínica</th>
              <th style="min-width: 150px;">Lançado por</th>
              <th style="min-width: 170px;">Motivo</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($itens as $item)
              <tr>
                <td>{{ $item->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>#{{ $item->baixa_vasilhame_id }}</td>
                <td class="fw-semibold">{{ $item->medicamento?->nome ?? '—' }}</td>
                <td class="font-monospace">{{ $item->codigo_barras ?? '—' }}</td>
                <td>{{ $item->lote ?? '—' }}</td>
                <td class="text-end fw-semibold">{{ $item->mg_baixa_formatado }}</td>
                <td>{{ $item->baixa?->clinica?->nome ?? '—' }}</td>
                <td>{{ $item->baixa?->user?->nome ?? '—' }}</td>
                <td class="small">{{ $item->motivo ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="text-end">Total baixado</th>
              <th class="text-end">{{ \App\Support\Numero::formatar($totalMg) }}</th>
              <th colspan="3"></th>
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
