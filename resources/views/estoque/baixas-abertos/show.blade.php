@extends('layouts.app')

@section('title', 'Baixa de medicamento aberto #'.$baixa->id)

@section('content')
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Baixa de medicamentos abertos #{{ $baixa->id }}</h4>

      <a href="{{ route('estoque.baixas-abertos.index') }}" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i>Voltar
      </a>
    </div>

    <div class="card-body">
      @if (session('success'))
        <div class="alert alert-success" role="alert">
          {{ session('success') }}
        </div>
      @endif

      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Clínica</small>
          <span class="fw-semibold">{{ $baixa->clinica?->nome ?? '—' }}</span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Data</small>
          <span class="fw-semibold">{{ $baixa->data_formatada ?? '—' }}</span>
        </div>

        <div class="col-md-3">
          <small class="text-muted d-block">Lançado por</small>
          <span class="fw-semibold">{{ $baixa->user?->nome ?? '—' }}</span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Total baixado</small>
          <span class="fw-semibold">{{ $baixa->total_mg_formatado }} mg</span>
        </div>

        @if ($baixa->observacao)
          <div class="col-12">
            <small class="text-muted d-block">Observação</small>
            <span>{{ $baixa->observacao }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h6 class="fw-semibold mb-0">Vasilhames baixados</h6>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width: 180px;">Medicamento</th>
              <th style="width: 140px;">Código de barras</th>
              <th style="width: 110px;">Lote</th>
              <th style="width: 115px;">Vencimento</th>
              <th style="width: 130px;" class="text-end">Baixado (mg)</th>
              <th style="width: 150px;" class="text-end">Restou no vasilhame</th>
              <th style="width: 130px;">Situação</th>
              <th style="min-width: 180px;">Motivo</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($baixa->itens as $item)
              @php $vasilhame = $item->vasilhameAberto; @endphp

              <tr>
                <td class="fw-semibold">{{ $item->medicamento?->nome ?? '—' }}</td>
                <td class="font-monospace">{{ $item->codigo_barras ?? '—' }}</td>
                <td>{{ $item->lote ?? '—' }}</td>
                <td>{{ $item->vencimento_formatado ?? '—' }}</td>
                <td class="text-end fw-semibold">{{ $item->mg_baixa_formatado }}</td>
                <td class="text-end">
                  {{ $vasilhame ? \App\Support\Numero::formatar($vasilhame->mg_restantes) . ' mg' : '—' }}
                </td>
                <td>
                  @if (! $vasilhame)
                    <span class="badge bg-label-secondary">—</span>
                  @elseif ($vasilhame->esta_em_uso)
                    <span class="badge bg-label-success">Em uso</span>
                  @else
                    <span class="badge bg-label-secondary">Encerrado</span>
                  @endif
                </td>
                <td class="small">{{ $item->motivo ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="text-end">Total baixado</th>
              <th class="text-end">{{ $baixa->total_mg_formatado }}</th>
              <th colspan="3"></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <small class="text-muted d-block mt-3">
        O saldo mostrado é o que restou no vasilhame depois desta baixa. Vasilhame "Em uso" continua aberto e
        pode ser aplicado; "Encerrado" zerou e não pode mais ser usado. Esta baixa não pode ser excluída.
      </small>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
